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

        $pendingUnfulfilledTotalAFN = Order::whereIn('status', ['pending', 'processing'])->where('currency', 'AFN')->sum('total_amount');
        $pendingUnfulfilledTotalUSD = Order::whereIn('status', ['pending', 'processing'])->where('currency', 'USD')->sum('total_amount');
        $totalReceivedAFN = Payment::where('currency', 'AFN')->sum('amount');
        $totalReceivedUSD = Payment::where('currency', 'USD')->sum('amount');
        $outstandingTotalAFN = Order::whereIn('status', ['pending', 'processing', 'completed'])
            ->where('currency', 'AFN')
            ->whereHas('payments')
            ->get()
            ->sum(function ($order) {
                return $order->remaining_amount > 0 ? $order->remaining_amount : 0;
            });
        $outstandingTotalUSD = Order::whereIn('status', ['pending', 'processing', 'completed'])
            ->where('currency', 'USD')
            ->whereHas('payments')
            ->get()
            ->sum(function ($order) {
                return $order->remaining_amount > 0 ? $order->remaining_amount : 0;
            });

        return view('payments.index', compact(
            'payments',
            'pendingUnfulfilledTotalAFN',
            'pendingUnfulfilledTotalUSD',
            'totalReceivedAFN',
            'totalReceivedUSD',
            'outstandingTotalAFN',
            'outstandingTotalUSD'
        ));
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
            'currency' => 'required|in:AFN,USD',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($validated['order_id']);
        
        if ($validated['amount'] > $order->remaining_amount) {
            $symbol = $order->currency === 'USD' ? '$' : 'افغ';
            return back()->with('error', 'د پیسو اندازه د پاتې پیسو څخه زیاته ده! (پاتې: ' . number_format($order->remaining_amount) . ' ' . $symbol . ')');
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

    public function customerPaymentsPage()
    {
        return view('payments.index');
    }

    public function customerIndex()
    {
        $payments = Payment::with(['order.customer'])->orderBy('created_at', 'desc')->get();
        $paymentsData = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'customer_name' => $payment->order->customer ? $payment->order->customer->name : 'Unknown',
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'created_at' => $payment->created_at->format('Y/m/d H:i'),
                'notes' => $payment->notes,
            ];
        });

        return response()->json([
            'payments' => $paymentsData,
        ]);
    }
}