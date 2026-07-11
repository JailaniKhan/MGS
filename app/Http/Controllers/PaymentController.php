<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\PartyPayment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        // Money received from sales (customer payments) via the structured Payment table.
        $incomingAFN = Payment::where('currency', 'AFN')->sum('amount');
        $incomingUSD = Payment::where('currency', 'USD')->sum('amount');

        // Money paid out for purchases (supplier payments) via the structured PurchasePayment table.
        $outgoingAFN = PurchasePayment::where('currency', 'AFN')->sum('amount');
        $outgoingUSD = PurchasePayment::where('currency', 'USD')->sum('amount');

        // Direct party payments recorded via the ledger (not linked to a document) must also be
        // counted, otherwise the totals ignore payments that are listed in "Recent transactions"
        // (a payment_received is inflow, a payment_made is outflow). These are disjoint from the
        // document-linked Payment / PurchasePayment rows in normal use, so no double counting.
        $ledgerInAFN  = PartyPayment::where('type', 'payment_received')->where('currency', 'AFN')->sum('amount');
        $ledgerInUSD  = PartyPayment::where('type', 'payment_received')->where('currency', 'USD')->sum('amount');
        $ledgerOutAFN = PartyPayment::where('type', 'payment_made')->where('currency', 'AFN')->sum('amount');
        $ledgerOutUSD = PartyPayment::where('type', 'payment_made')->where('currency', 'USD')->sum('amount');

        $incomingAFN += $ledgerInAFN;
        $incomingUSD += $ledgerInUSD;
        $outgoingAFN += $ledgerOutAFN;
        $outgoingUSD += $ledgerOutUSD;

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
                    'description' => ($p->order?->party?->name ?? __('messages.unknown')) . ' - ' . __('messages.order') . ' #' . $p->order_id,
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
                    'description' => ($p->purchase?->party?->name ?? __('messages.unknown')) . ' - ' . __('messages.purchase') . ' #' . $p->purchase_id,
                    'notes' => $p->notes,
                    'date' => $p->created_at,
                ];
            });

        // Ledger transactions
        $ledgerEntries = PartyPayment::get();
        // Load person names efficiently based on person_type
        $customerIds = $ledgerEntries->where('person_type', 'customer')->pluck('person_id')->unique();
        $supplierIds = $ledgerEntries->where('person_type', 'supplier')->pluck('person_id')->unique();
        $customerNames = Customer::whereIn('id', $customerIds)->pluck('name', 'id');
        $supplierNames = Supplier::whereIn('id', $supplierIds)->pluck('name', 'id');
        
        $ledgerTransactions = $ledgerEntries->map(function ($entry) use ($customerNames, $supplierNames) {
            $personName = $entry->person_type === 'customer' 
                ? ($customerNames[$entry->person_id] ?? __('messages.unknown'))
                : ($supplierNames[$entry->person_id] ?? __('messages.unknown'));
            $typeLabel = $entry->type === 'payment_received' ? 'incoming' : 'outgoing';
            return [
                'type' => $typeLabel,
                'amount' => $entry->amount,
                'currency' => $entry->currency,
                'description' => $personName . ' (' . __('messages.ledger_close') . ')',
                'notes' => ($entry->notes ? $entry->notes : '') . ' - ' . __('messages.ledger'),
                'date' => $entry->created_at,
            ];
        });

        $transactions = collect($incomingTransactions)
            ->concat($outgoingTransactions)
            ->concat($ledgerTransactions)
            ->sortByDesc('date')
            ->values()
            ->take(50);

        // Outstanding balances: receivables (orders — both customer and supplier) and
        // payables (purchases). These were previously absent from the payments page, so
        // supplier-created orders never showed up here.
        $receivables = Order::with(['customer', 'supplier'])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->filter(fn($o) => $o->remaining_amount > 0)
            ->sortByDesc('created_at')
            ->values();

        $payables = Purchase::with('supplier')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->filter(fn($p) => $p->remaining_amount > 0)
            ->sortByDesc('created_at')
            ->values();

        return view('payments.index', compact(
            'incomingAFN', 'incomingUSD',
            'outgoingAFN', 'outgoingUSD',
            'balanceAFN', 'balanceUSD',
            'transactions',
            'receivables', 'payables'
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
            $symbol = $order->currency === 'USD' ? '$' : __('messages.afn');
            return back()->with('error', __('messages.payment_exceeds_balance') . ' (' . __('messages.pending') . ': ' . number_format($order->remaining_amount) . ' ' . $symbol . ')');
        }

        Payment::create($validated);

        return redirect()->route('payments.index')->with('success', __('messages.payment_created'));
    }

    public function show(Order $order)
    {
        $order->load('customer', 'payments');
        return view('payments.show', compact('order'));
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return redirect()->route('payments.index')->with('success', __('messages.payment_deleted'));
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
                'customer_name' => $payment->order->party ? $payment->order->party->name : 'Unknown',
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