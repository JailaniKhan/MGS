<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AddsListBalances;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use AddsListBalances;

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
        $ledgerInAFN = PartyPayment::where('type', 'payment_received')->where('currency', 'AFN')->sum('amount');
        $ledgerInUSD = PartyPayment::where('type', 'payment_received')->where('currency', 'USD')->sum('amount');
        $ledgerOutAFN = PartyPayment::where('type', 'payment_made')->where('currency', 'AFN')->sum('amount');
        $ledgerOutUSD = PartyPayment::where('type', 'payment_made')->where('currency', 'USD')->sum('amount');

        $incomingAFN += $ledgerInAFN;
        $incomingUSD += $ledgerInUSD;
        $outgoingAFN += $ledgerOutAFN;
        $outgoingUSD += $ledgerOutUSD;

        // Wallet balance
        $balanceAFN = $incomingAFN - $outgoingAFN;
        $balanceUSD = $incomingUSD - $outgoingUSD;

        // Recent transactions (combine incoming and outgoing). Cap each source so
        // the wallet page does not load the entire payment history into memory.
        $feedLimit = self::PER_PAGE * 5;

        $incomingTransactions = Payment::with(['order.customer', 'order.supplier'])
            ->latest()
            ->limit($feedLimit)
            ->get()
            ->map(function ($p) {
                return [
                    'type' => 'incoming',
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'description' => ($p->order?->party?->name ?? __('messages.unknown')).' - '.__('messages.order').' #'.$p->order_id,
                    'notes' => $p->notes,
                    'date' => $p->created_at,
                    'link' => route('orders.show', $p->order_id),
                ];
            });

        $outgoingTransactions = PurchasePayment::latest()->limit($feedLimit)->get();

        // Party names resolve without user scopes: purchase payments and ledger
        // payments are shop-wide (no user_id), so the people they reference may
        // belong to any user. Load names once for both feeds.
        $purchases = Purchase::withoutGlobalScopes()
            ->whereIn('id', $outgoingTransactions->pluck('purchase_id')->unique())
            ->get(['id', 'person_type', 'person_id']);

        $purchaseParty = $purchases->mapWithKeys(function ($purchase) {
            return [$purchase->id => ['type' => $purchase->person_type, 'id' => $purchase->person_id]];
        });

        $ledgerEntries = PartyPayment::latest()->limit($feedLimit)->get();

        $customerIds = $purchases->where('person_type', 'customer')->pluck('person_id')
            ->concat($ledgerEntries->where('person_type', 'customer')->pluck('person_id'))
            ->unique();
        $supplierIds = $purchases->where('person_type', 'supplier')->pluck('person_id')
            ->concat($ledgerEntries->where('person_type', 'supplier')->pluck('person_id'))
            ->unique();
        $customerNames = Customer::withoutGlobalScopes()->whereIn('id', $customerIds)->pluck('name', 'id');
        $supplierNames = Supplier::withoutGlobalScopes()->whereIn('id', $supplierIds)->pluck('name', 'id');

        $purchasePartyNames = $purchaseParty->map(function ($ref) use ($customerNames, $supplierNames) {
            return $ref['type'] === 'customer'
                ? ($customerNames[$ref['id']] ?? null)
                : ($supplierNames[$ref['id']] ?? null);
        });

        $outgoingTransactions = $outgoingTransactions->map(function ($p) use ($purchasePartyNames) {
            return [
                'type' => 'outgoing',
                'amount' => $p->amount,
                'currency' => $p->currency,
                'description' => ($purchasePartyNames[$p->purchase_id] ?? __('messages.unknown')).' - '.__('messages.purchase').' #'.$p->purchase_id,
                'notes' => $p->notes,
                'date' => $p->created_at,
                'link' => route('purchases.show', $p->purchase_id),
            ];
        });

        $ledgerTransactions = $ledgerEntries->map(function ($entry) use ($customerNames, $supplierNames) {
            $personName = $entry->person_type === 'customer'
                ? ($customerNames[$entry->person_id] ?? __('messages.unknown'))
                : ($supplierNames[$entry->person_id] ?? __('messages.unknown'));
            $typeLabel = $entry->type === 'payment_received' ? 'incoming' : 'outgoing';

            return [
                'type' => $typeLabel,
                'amount' => $entry->amount,
                'currency' => $entry->currency,
                'description' => $personName.' ('.__('messages.ledger_close').')',
                'notes' => $entry->notes,
                'date' => $entry->created_at,
                'link' => route('ledger.show', ['type' => $entry->person_type, 'id' => $entry->person_id]),
            ];
        });

        $allTransactions = collect($incomingTransactions)
            ->concat($outgoingTransactions)
            ->concat($ledgerTransactions)
            ->sortByDesc('date')
            ->values();

        $transactions = $this->paginateCollection($allTransactions, self::PER_PAGE, 'tx_page');

        // Outstanding balances: receivables (orders — both customer and supplier) and
        // payables (purchases), split per currency so the page can show them separately.
        // Balances are attached in one grouped query each via the trait, never the
        // per-row accessors (which would run 2 SUM queries per row).
        $outstandingOrders = Order::with(['customer', 'supplier'])
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(100)
            ->get();
        $this->attachOrderListBalances($outstandingOrders);
        $outstandingOrders = $outstandingOrders->filter(fn ($o) => bccomp($o->remaining, '0', 2) > 0);

        $receivablesAFN = $this->paginateCollection(
            $outstandingOrders->filter(fn ($o) => $o->currency === 'AFN')->values(),
            self::PER_PAGE,
            'recv_afn_page'
        );

        $receivablesUSD = $this->paginateCollection(
            $outstandingOrders->filter(fn ($o) => $o->currency === 'USD')->values(),
            self::PER_PAGE,
            'recv_usd_page'
        );

        $outstandingPurchases = Purchase::with(['customer', 'supplier'])
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(100)
            ->get();
        $this->attachPurchaseListBalances($outstandingPurchases);
        $outstandingPurchases = $outstandingPurchases->filter(fn ($p) => bccomp($p->remaining, '0', 2) > 0);

        $payablesAFN = $this->paginateCollection(
            $outstandingPurchases->filter(fn ($p) => $p->currency === 'AFN')->values(),
            self::PER_PAGE,
            'pay_afn_page'
        );

        $payablesUSD = $this->paginateCollection(
            $outstandingPurchases->filter(fn ($p) => $p->currency === 'USD')->values(),
            self::PER_PAGE,
            'pay_usd_page'
        );

        return view('payments.index', compact(
            'incomingAFN', 'incomingUSD',
            'outgoingAFN', 'outgoingUSD',
            'balanceAFN', 'balanceUSD',
            'transactions',
            'receivablesAFN', 'receivablesUSD',
            'payablesAFN', 'payablesUSD'
        ));
    }

    public function create()
    {
        // The party may be a customer OR a supplier, so both must be eager-loaded.
        $orders = Order::with(['customer', 'supplier'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->get();
        $this->attachOrderListBalances($orders);
        $orders = $orders->filter(function ($order) {
            return bccomp($order->remaining, '0', 2) > 0;
        })->values();

        // Purchases too — this page records payments against both orders and purchases.
        $purchases = Purchase::with(['customer', 'supplier'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->get();
        $this->attachPurchaseListBalances($purchases);
        $purchases = $purchases->filter(function ($purchase) {
            return bccomp($purchase->remaining, '0', 2) > 0;
        })->values();

        $selectedType = in_array(request('type'), ['order', 'purchase'], true) ? request('type') : 'order';
        $selectedOrderId = request('order_id');
        $selectedPurchaseId = request('purchase_id');

        return view('payments.create', compact('orders', 'purchases', 'selectedType', 'selectedOrderId', 'selectedPurchaseId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:order,purchase',
            'order_id' => 'required_if:type,order|exists:orders,id',
            'purchase_id' => 'required_if:type,purchase|exists:purchases,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validated['type'] === 'order') {
            $document = Order::findOrFail($validated['order_id']);
            $remaining = $document->remaining_amount;
        } else {
            $document = Purchase::findOrFail($validated['purchase_id']);
            $remaining = $document->remaining_amount;
        }

        if ($validated['currency'] !== $document->currency) {
            return back()->with('error', __('messages.payment_currency_mismatch'));
        }

        if ($validated['amount'] > $remaining) {
            $symbol = $document->currency === 'USD' ? '$' : __('messages.afn');

            return back()->with('error', __('messages.payment_exceeds_balance').' ('.__('messages.pending').': '.number_format($remaining).' '.$symbol.')');
        }

        if ($validated['type'] === 'order') {
            Payment::create([
                'order_id' => $document->id,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'notes' => $validated['notes'] ?? null,
            ]);
        } else {
            PurchasePayment::create([
                'purchase_id' => $document->id,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'notes' => $validated['notes'] ?? null,
            ]);
        }

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
        return $this->index();
    }

    public function customerIndex()
    {
        $payments = Payment::with(['order.customer'])->orderBy('created_at', 'desc')->paginate(self::PER_PAGE)->withQueryString();
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
