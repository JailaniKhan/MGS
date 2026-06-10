<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::withCount('orders')->orderBy('name')->get();
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

        return redirect()->route('customers.index')->with('success', 'ګیراک په بریالیتوب سره اضافه شو!');
    }

    public function show(Customer $customer)
    {
        $customer->load('orders.orderItems.product');
        return view('customers.show', compact('customer'));
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

        return redirect()->route('customers.index')->with('success', 'ګیراک په بریالیتوب سره سم شو!');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'ګیراک په بریالیتوب سره ړنګ شو!');
    }
}