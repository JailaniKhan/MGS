<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PartyPayment;
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

        $orderIds = Order::where('person_type', 'customer')->where('person_id', $customer->id)->pluck('id');

        $paymentsFromOrdersAFN = Payment::whereIn('order_id', $orderIds)->where('currency', 'AFN')->sum('amount');
        $paymentsFromOrdersUSD = Payment::whereIn('order_id', $orderIds)->where('currency', 'USD')->sum('amount');

        $ledgerPaymentsAFN = PartyPayment::where('person_type', 'customer')
            ->where('person_id', $customer->id)
            ->where('currency', 'AFN')
            ->where('type', 'payment_received')
            ->sum('amount');

        $ledgerPaymentsUSD = PartyPayment::where('person_type', 'customer')
            ->where('person_id', $customer->id)
            ->where('currency', 'USD')
            ->where('type', 'payment_received')
            ->sum('amount');

        $totalAFN = Order::whereIn('id', $orderIds)->where('currency', 'AFN')->sum('total_amount');
        $totalUSD = Order::whereIn('id', $orderIds)->where('currency', 'USD')->sum('total_amount');
        $paidAFN = $paymentsFromOrdersAFN + $ledgerPaymentsAFN;
        $paidUSD = $paymentsFromOrdersUSD + $ledgerPaymentsUSD;

        return view('customers.show', compact(
            'customer', 'totalAFN', 'totalUSD', 'paidAFN', 'paidUSD'
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