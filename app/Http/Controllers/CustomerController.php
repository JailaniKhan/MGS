<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Billing\PartyBalanceService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::withCount('orders')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', __('messages.customer_created'));
    }

    public function show(Customer $customer)
    {
        $customer->load('orders.orderItems.product');

        // Canonical balance: only non-cancelled docs, same-currency payments,
        // returns reduce, per-doc clamp — matches orders, wallet and ledger.
        $balance = app(PartyBalanceService::class)->partySummary('customer', $customer->id);

        $totalAFN = (float) $balance['total_amount_afn'];
        $totalUSD = (float) $balance['total_amount_usd'];
        $paidAFN = (float) $balance['paid_afn'];
        $paidUSD = (float) $balance['paid_usd'];
        $remainingAFN = (float) $balance['remaining_afn'];
        $remainingUSD = (float) $balance['remaining_usd'];

        return view('customers.show', compact(
            'customer', 'totalAFN', 'totalUSD', 'paidAFN', 'paidUSD', 'remainingAFN', 'remainingUSD'
        ));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', __('messages.customer_updated'));
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->route('customers.index')->with('success', __('messages.customer_deleted'));
    }
}