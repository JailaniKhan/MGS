<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with('order.customer')->orderBy('created_at', 'desc')->get();
        return view('payments.index', compact('payments'));
    }

    public function create()
    {
        $orders = Order::with('customer')->where('status', '!=', 'cancelled')->orderBy('created_at', 'desc')->get();
        $orders = $orders->filter(function ($order) {
            return $order->remaining_amount > 0;
        });
        $selectedOrderId = request('order_id');
        return view('payments.create', compact('orders', 'selectedOrderId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($validated['order_id']);
        
        if ($validated['amount'] > $order->remaining_amount) {
            return back()->with('error', 'د پیسو اندازه د پاتې پیسو څخه زیاته ده! (پاتې: ' . number_format($order->remaining_amount) . ' افغ)');
        }

        Payment::create($validated);

        return redirect()->route('payments.index')->with('success', 'پیسې په بریالیتوب سره ثبت شوې!');
    }

    public function show(Order $order)
    {
        $order->load('customer', 'payments');
        return view('payments.show', compact('order'));
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return redirect()->route('payments.index')->with('success', 'د پیسو ثبت ړنګ شو!');
    }
}