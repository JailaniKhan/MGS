<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'label' => $unit->name . ($unit->short_name ? ' (' . $unit->short_name . ')' : ''),
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
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $validated['lot_number'] = $this->resolveLotNumber($validated['lot_number'] ?? null);

        Product::create($validated);

        return redirect()->route('products.index')->with('success', __('messages.product_created'));
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
            'label' => $unit->name . ($unit->short_name ? ' (' . $unit->short_name . ')' : ''),
        ])->values()->all();
        return view('products.edit', compact('product', 'categories', 'units', 'categoryOptions', 'unitOptions'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255|unique:products,barcode,' . $product->id,
            'lot_number' => 'nullable|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        // Keep the current lot when the field is left empty on edit.
        if (trim($validated['lot_number'] ?? '') === '') {
            unset($validated['lot_number']);
        }

        $oldStock = $product->stock;
        $product->update($validated);
        $newStock = $product->fresh()->stock;

        if ($oldStock !== $newStock) {
            $change = $newStock - $oldStock;
            StockMovement::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'quantity_change' => $change,
                'movement_type' => 'adjustment',
                'reference_type' => 'product',
                'reference_id' => $product->id,
                'notes' => __('messages.stock_adjusted'),
            ]);
        }

        return redirect()->route('products.index')->with('success', __('messages.product_updated'));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', __('messages.product_deleted'));
    }

    private function resolveLotNumber(?string $lotNumber): string
    {
        if ($lotNumber !== null && trim($lotNumber) !== '') {
            return trim($lotNumber);
        }

        return 'LOT-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
    }
}