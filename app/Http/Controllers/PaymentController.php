<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        // Money received from sales (customer payments) - existing + ledger
        $incomingAFN = Payment::where('currency', 'AFN')->sum('amount')
            + LedgerEntry::where('currency', 'AFN')->where('type', 'payment_received')->sum('amount');
        $incomingUSD = Payment::where('currency', 'USD')->sum('amount')
            + LedgerEntry::where('currency', 'USD')->where('type', 'payment_received')->sum('amount');

        // Money paid out for purchases (supplier payments) - existing + ledger
        $outgoingAFN = PurchasePayment::where('currency', 'AFN')->sum('amount')
            + LedgerEntry::where('currency', 'AFN')->where('type', 'payment_made')->sum('amount');
        $outgoingUSD = PurchasePayment::where('currency', 'USD')->sum('amount')
            + LedgerEntry::where('currency', 'USD')->where('type', 'payment_made')->sum('amount');

        // Wallet balance
        $balanceAFN = $incomingAFN - $outgoingAFN;
        $balanceUSD = $incomingUSD - $outgoingUSD;

        // Recent transactions (combine incoming and outgoing)
        $incomingTransactions = Payment::with('order.customer')
            ->get()
            ->map(function ($p) {
                return [
                    'type' => 'incoming',
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'description' => $p->order?->customer?->name . ' - امر #' . $p->order_id,
                    'notes' => $p->notes,
                    'date' => $p->created_at,
                ];
            });

        $outgoingTransactions = PurchasePayment::with('purchase.supplier')
            ->get()
            ->map(function ($p) {
                return [
                    'type' => 'outgoing',
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'description' => $p->purchase?->supplier?->name . ' - خرید #' . $p->purchase_id,
                    'notes' => $p->notes,
                    'date' => $p->created_at,
                ];
            });

        // Ledger transactions
        $ledgerTransactions = LedgerEntry::with('person')
            ->get()
            ->map(function ($entry) {
                $personName = $entry->person?->name ?? 'نامعلوم';
                $typeLabel = $entry->type === 'payment_received' ? 'incoming' : 'outgoing';
                $description = $typeLabel === 'incoming'
                    ? $personName . ' (روزنامچه)'
                    : $personName . ' (روزنامچه)';
                return [
                    'type' => $typeLabel,
                    'amount' => $entry->amount,
                    'currency' => $entry->currency,
                    'description' => $description,
                    'notes' => ($entry->notes ? $entry->notes : '') . ' - روزنامچه',
                    'date' => $entry->created_at,
                ];
            });

        $transactions = collect($incomingTransactions)
            ->concat($outgoingTransactions)
            ->concat($ledgerTransactions)
            ->sortByDesc('date')
            ->values()
            ->take(50);

        return view('payments.index', compact(
            'incomingAFN', 'incomingUSD',
            'outgoingAFN', 'outgoingUSD',
            'balanceAFN', 'balanceUSD',
            'transactions'
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