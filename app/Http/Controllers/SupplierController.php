<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\Billing\PartyBalanceService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount('purchases')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', __('messages.supplier_created'));
    }

    public function show(Supplier $supplier)
    {
        $supplier->load('purchases');

        // Canonical balance: only non-cancelled docs, same-currency payments,
        // returns reduce, per-doc clamp — matches purchases, wallet and ledger.
        $balance = app(PartyBalanceService::class)->partySummary('supplier', $supplier->id);

        $totalAFN = (float) $balance['total_amount_afn'];
        $totalUSD = (float) $balance['total_amount_usd'];
        $paidAFN = (float) $balance['paid_afn'];
        $paidUSD = (float) $balance['paid_usd'];
        $remainingAFN = (float) $balance['remaining_afn'];
        $remainingUSD = (float) $balance['remaining_usd'];

        return view('suppliers.show', compact(
            'supplier', 'totalAFN', 'totalUSD', 'paidAFN', 'paidUSD', 'remainingAFN', 'remainingUSD'
        ));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', __('messages.supplier_updated'));
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('messages.supplier_deleted'));
    }
}
