<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::with('purchase.supplier')
            ->latest('expense_date')->paginate(self::PER_PAGE)->withQueryString();

        // All-time summary: the list itself has always been unfiltered, so the
        // tiles now report the same scope instead of a month-only subset.
        $allExpenses = Expense::get(['amount', 'currency']);
        $totalCount = $allExpenses->count();
        $totalAFN = $allExpenses->where('currency', 'AFN')->sum('amount');
        $totalUSD = $allExpenses->where('currency', 'USD')->sum('amount');

        return view('expenses.index', compact('expenses', 'totalCount', 'totalAFN', 'totalUSD'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->pluck('name');
        $categoryOptions = $this->categoryOptions($categories);

        $effective = old('category');
        $pickerSelected = $effective && ! $categories->contains($effective) ? '__other__' : $effective;

        $purchaseOptions = $this->purchaseOptions();

        return view('expenses.create', compact('categories', 'categoryOptions', 'pickerSelected', 'purchaseOptions'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateExpense($request);

        Expense::create(array_merge($validated, ['user_id' => Auth::id()]));

        return redirect()->route('expenses.index')->with('success', __('messages.expense_created'));
    }

    public function show(Expense $expense)
    {
        $expense->load('purchase.supplier', 'purchase.purchaseItems');

        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense)
    {
        $categories = Category::orderBy('name')->pluck('name');
        $categoryOptions = $this->categoryOptions($categories);

        $effective = old('category', $expense->category);
        $pickerSelected = $effective && ! $categories->contains($effective) ? '__other__' : $effective;

        $purchaseOptions = $this->purchaseOptions();
        // Offer the currently linked purchase even if it is old/paginated out.
        if ($expense->purchase_id && ! collect($purchaseOptions)->contains('value', (string) $expense->purchase_id)) {
            $expense->purchase->loadMissing('supplier', 'purchaseItems.product');
            $purchaseOptions[] = $this->purchaseOption($expense->purchase);
        }

        return view('expenses.edit', compact('expense', 'categories', 'categoryOptions', 'pickerSelected', 'purchaseOptions'));
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $this->validateExpense($request);

        $expense->update($validated);

        return redirect()->route('expenses.index')->with('success', __('messages.expense_updated'));
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', __('messages.expense_deleted'));
    }

    private function validateExpense(Request $request): array
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'receipt_path' => 'nullable|string|max:500',
            'purchase_id' => 'nullable|integer',
        ]);

        // An attached purchase must belong to the user, be non-cancelled, and
        // the expense must be booked in the purchase's own currency (a foreign
        // currency must never distort the linked purchase's cost basis).
        if (! empty($validated['purchase_id'])) {
            // Bypass the user global scope to distinguish "not found at all"
            // (404) from "someone else's purchase" (403) with a clear message.
            $purchase = Purchase::withoutGlobalScopes()
                ->where('id', $validated['purchase_id'])
                ->first();

            if ($purchase === null) {
                abort(404);
            }

            if ($purchase->user_id !== Auth::id()) {
                abort(403);
            }

            if ($purchase->status === 'cancelled') {
                $this->rejectPurchaseLink(__('messages.purchase_cancelled'));
            }

            if ($purchase->currency !== $validated['currency']) {
                $this->rejectPurchaseLink(__('messages.expense_currency_must_match_purchase'));
            }
        } else {
            $validated['purchase_id'] = null;
        }

        return $validated;
    }

    private function rejectPurchaseLink(string $message): never
    {
        throw ValidationException::withMessages(['purchase_id' => $message]);
    }

    private function purchaseOptions(): array
    {
        return Purchase::with(['supplier', 'purchaseItems.product'])
            ->where('status', '!=', 'cancelled')
            ->latest('created_at')
            ->limit(60)
            ->get()
            ->map(fn ($purchase) => $this->purchaseOption($purchase))
            ->all();
    }

    private function categoryOptions($categories): array
    {
        return $categories
            ->map(fn (string $name) => ['value' => $name, 'label' => $name])
            ->push(['value' => '__other__', 'label' => __('messages.other')])
            ->values()
            ->all();
    }

    private function purchaseOption(Purchase $purchase): array
    {
        $items = $purchase->purchaseItems;

        $lots = $items
            ->pluck('lot_number')
            ->filter(fn ($lot) => $lot !== null && trim((string) $lot) !== '')
            ->unique()
            ->values();

        // "Lot 1,2" when every line shares one lot, else the first lots + "…".
        $lotsText = $lots->isNotEmpty()
            ? __('messages.lot_short').' '.implode(',', $lots->take(3)->all()).($lots->count() > 3 ? '…' : '')
            : '';

        $productNames = $items
            ->map(fn ($item) => $item->product?->name)
            ->filter()
            ->unique()
            ->take(3)
            ->implode(', ');

        $label = collect([$lotsText, $productNames])
            ->filter()
            ->implode(' · ');

        return [
            'value' => (string) $purchase->id,
            'label' => trim('#'.$purchase->id.($label !== '' ? ' · '.$label : '')),
            'sublabel' => trim(($purchase->supplier?->name ?? '').' · '.$purchase->created_at?->format('Y-m-d')),
            'price' => rtrim(rtrim(number_format((float) $purchase->total_amount, 2, '.', ''), '0'), '.'),
            'price_currency' => $purchase->currency,
            'purchase_id' => (string) $purchase->id,
            'lots' => $lots->implode(','),
            'products' => $productNames,
        ];
    }
}
