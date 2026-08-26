<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'unit')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        $categoryOptions = $categories->map(fn ($category) => [
            'value' => $category->id,
            'label' => $category->name,
        ])->values()->all();
        $unitOptions = $units->map(fn ($unit) => [
            'value' => $unit->id,
            'label' => $unit->name.($unit->short_name ? ' ('.$unit->short_name.')' : ''),
        ])->values()->all();

        return view('products.create', compact('categories', 'units', 'categoryOptions', 'unitOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255|unique:products,barcode',
            'lot_number' => 'nullable|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'price_currency' => 'required|in:AFN,USD',
            'price' => 'nullable|numeric|min:0',
            'stock_afn' => 'required|integer|min:0',
            'stock_usd' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        // A lot is priced in exactly one currency; the other side stays empty.
        // A product's stock follows the same rule: a new product cannot open
        // a pool in the currency it is not priced in.
        $this->guardOtherPool($validated['price_currency'], (int) $validated['stock_afn'], (int) $validated['stock_usd']);

        [$validated['price'], $validated['price_usd']] = $this->singleCurrencyPrice(
            $validated['price_currency'],
            (float) ($validated['price'] ?? 0)
        );
        unset($validated['price_currency']);

        $validated['lot_number'] = $this->resolveLotNumber($validated['lot_number'] ?? null);
        // Legacy combined column kept in sync for aggregate queries.
        $validated['stock'] = (int) $validated['stock_afn'] + (int) $validated['stock_usd'];

        Product::create($validated);

        return redirect()->route('inventory.index')->with('success', __('messages.product_created'));
    }

    public function show(Product $product)
    {
        $product->load('category', 'unit');
        $threshold = (int) Setting::get('min_stock_threshold', 10);

        // The lot on the shelf per pool: latest real purchase lot, with the
        // product's own master field as the fallback.
        $latestLots = Product::latestPurchaseLots([$product->id]);
        $poolLots = [
            'AFN' => $latestLots->get($product->id.':AFN') ?: $product->lot_number,
            'USD' => $latestLots->get($product->id.':USD') ?: $product->lot_number,
        ];

        $movements = StockMovement::where('product_id', $product->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('products.show', compact(
            'product', 'poolLots', 'threshold', 'movements'
        ));
    }

    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        $categoryOptions = $categories->map(fn ($category) => [
            'value' => $category->id,
            'label' => $category->name,
        ])->values()->all();
        $unitOptions = $units->map(fn ($unit) => [
            'value' => $unit->id,
            'label' => $unit->name.($unit->short_name ? ' ('.$unit->short_name.')' : ''),
        ])->values()->all();

        return view('products.edit', compact('product', 'categories', 'units', 'categoryOptions', 'unitOptions'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255|unique:products,barcode,'.$product->id,
            'lot_number' => 'nullable|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'price_currency' => 'required|in:AFN,USD',
            'price' => 'nullable|numeric|min:0',
            'stock_afn' => 'required|integer|min:0',
            'stock_usd' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        // Once a product holds a price its currency is locked — switching it
        // would strand stock in the old pool.
        $lockedCurrency = (float) $product->price > 0 ? 'AFN'
            : ((float) ($product->price_usd ?? 0) > 0 ? 'USD' : null);

        if ($lockedCurrency !== null && $validated['price_currency'] !== $lockedCurrency) {
            throw ValidationException::withMessages([
                'price_currency' => __('messages.price_currency_locked', [
                    'currency' => $lockedCurrency === 'USD' ? __('messages.usd') : __('messages.afn'),
                ]),
            ]);
        }

        // A product's stock lives in the pool of its priced currency. The
        // other pool may shrink (a currency switch moves stock out of it)
        // but never grow.
        $this->guardOtherPool(
            $validated['price_currency'],
            (int) $validated['stock_afn'],
            (int) $validated['stock_usd'],
            $product->stockFor('AFN'),
            $product->stockFor('USD')
        );

        // Leaving the price empty keeps the amount already stored in the
        // selected currency.
        $amount = trim((string) ($validated['price'] ?? ''));
        $amount = $amount === ''
            ? (float) ($validated['price_currency'] === 'USD' ? ($product->price_usd ?? 0) : $product->price)
            : (float) $amount;

        // A lot is priced in exactly one currency; picking the other side
        // clears the previous one.
        [$validated['price'], $validated['price_usd']] = $this->singleCurrencyPrice(
            $validated['price_currency'],
            $amount
        );
        unset($validated['price_currency']);

        // Keep the current lot when the field is left empty on edit.
        if (trim($validated['lot_number'] ?? '') === '') {
            unset($validated['lot_number']);
        }

        // Manual pool adjustments: one movement per changed pool.
        $poolDeltas = [
            'AFN' => (int) $validated['stock_afn'] - $product->stockFor('AFN'),
            'USD' => (int) $validated['stock_usd'] - $product->stockFor('USD'),
        ];

        $validated['stock'] = (int) $validated['stock_afn'] + (int) $validated['stock_usd'];
        $product->update($validated);

        foreach ($poolDeltas as $currency => $change) {
            if ($change === 0) {
                continue;
            }

            StockMovement::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'quantity_change' => $change,
                'currency' => $currency,
                'movement_type' => 'adjustment',
                'reference_type' => 'product',
                'reference_id' => $product->id,
                'notes' => __('messages.stock_adjusted'),
            ]);
        }

        return redirect()->route('inventory.index')->with('success', __('messages.product_updated'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('inventory.index')->with('success', __('messages.product_deleted'));
    }

    private function resolveLotNumber(?string $lotNumber): string
    {
        if ($lotNumber !== null && trim($lotNumber) !== '') {
            return trim($lotNumber);
        }

        // Lots are plain sequential numbers (1, 2, 3...). The user global
        // scope keeps each owner's sequence separate.
        $max = Product::query()
            ->whereNotNull('lot_number')
            ->where('lot_number', '<>', '')
            ->get(['lot_number'])
            ->map(fn ($product) => trim((string) $product->lot_number))
            ->filter(fn ($lot) => ctype_digit($lot))
            ->map(fn ($lot) => (int) $lot)
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    /**
     * A product is priced in exactly one currency — the other side has no
     * price of its own. AFN keeps 0 (NOT NULL column), USD keeps null.
     *
     * @return array{0: float, 1: float|null} [price, price_usd]
     */
    private function singleCurrencyPrice(string $currency, float $amount): array
    {
        return $currency === 'USD'
            ? [0.0, $amount > 0 ? $amount : null]
            : [$amount, null];
    }

    /**
     * Guard the pool that does NOT match the priced currency: it may shrink
     * (switching the price currency moves stock out of it) but never grow —
     * growing it would punch the same cross-currency hole a mismatched
     * purchase would. New products pass zeros for both current pools.
     */
    private function guardOtherPool(string $priceCurrency, int $stockAfn, int $stockUsd, int $currentAfn = 0, int $currentUsd = 0): void
    {
        if ($priceCurrency === 'USD' && $stockAfn > $currentAfn) {
            throw ValidationException::withMessages([
                'stock_afn' => __('messages.stock_currency_mismatch', ['currency' => __('messages.usd')]),
            ]);
        }

        if ($priceCurrency === 'AFN' && $stockUsd > $currentUsd) {
            throw ValidationException::withMessages([
                'stock_usd' => __('messages.stock_currency_mismatch', ['currency' => __('messages.afn')]),
            ]);
        }
    }
}
