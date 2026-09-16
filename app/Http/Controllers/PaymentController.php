<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AddsListBalances;
use App\Models\CashbookEntry;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Services\Accounting\CashFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    use AddsListBalances;

    public function index(CashFlowService $cashFlow)
    {
        // Wallet hero: the canonical cash-flow union (payments, ledger
        // payments, BOTH cashbook generations, expenses, salaries) — the
        // same figure every other cash surface shows.
        $totals = $cashFlow->totals();

        $incomingAFN = $totals['AFN']['in'];
        $incomingUSD = $totals['USD']['in'];
        $outgoingAFN = $totals['AFN']['out'];
        $outgoingUSD = $totals['USD']['out'];

        // Wallet balance
        $balanceAFN = $totals['AFN']['balance'];
        $balanceUSD = $totals['USD']['balance'];

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

        // Cashbook entries — BOTH generations, so every amount that moves the
        // hero has a matching line in the feed. The journal rows link to the
        // cashbook page; the party name comes from the journal's morphTo
        // reference (already resolved names cover the shared people).
        $cashbookJournals = JournalEntry::with(['reference', 'ledgerEntries'])
            ->whereIn('source', ['cashbook_in', 'cashbook_out'])
            ->latest('transaction_date')
            ->limit($feedLimit)
            ->get();

        $cashbookTransactions = $cashbookJournals->map(function ($journal) {
            $cashLine = $journal->ledgerEntries->first();
            $isIn = $journal->source === 'cashbook_in';
            $notes = collect($journal->ledgerEntries)->pluck('notes')->filter()->first();
            $description = trim(implode(' - ', array_filter([
                $journal->description,
                $journal->reference?->name,
                $notes,
            ])));

            return [
                'type' => $isIn ? 'incoming' : 'outgoing',
                'amount' => $cashLine?->amount ?? 0,
                'currency' => $journal->currency,
                'description' => $description !== '' ? $description : ($isIn ? __('messages.cash_income') : __('messages.cash_expense')),
                'notes' => $notes,
                'date' => $journal->transaction_date,
                'link' => route('cashbook.index'),
            ];
        });

        $legacyCashbook = CashbookEntry::query()
            ->latest()
            ->limit($feedLimit)
            ->get()
            ->map(function ($entry) {
                return [
                    'type' => $entry->type === 'in' ? 'incoming' : 'outgoing',
                    'amount' => $entry->amount,
                    'currency' => $entry->currency,
                    'description' => $entry->type === 'in' ? __('messages.cash_income') : __('messages.cash_expense'),
                    'notes' => $entry->notes,
                    'date' => $entry->entry_date,
                    'link' => route('cashbook.index'),
                ];
            });

        $allTransactions = collect($incomingTransactions)
            ->concat($outgoingTransactions)
            ->concat($ledgerTransactions)
            ->concat($cashbookTransactions)
            ->concat($legacyCashbook)
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

        // Outstanding totals for the wallet hero summary tiles.
        $receivablesTotalAFN = $outstandingOrders->where('currency', 'AFN')->sum('remaining');
        $receivablesTotalUSD = $outstandingOrders->where('currency', 'USD')->sum('remaining');
        $payablesTotalAFN = $outstandingPurchases->where('currency', 'AFN')->sum('remaining');
        $payablesTotalUSD = $outstandingPurchases->where('currency', 'USD')->sum('remaining');

        return view('payments.index', compact(
            'incomingAFN', 'incomingUSD',
            'outgoingAFN', 'outgoingUSD',
            'balanceAFN', 'balanceUSD',
            'transactions',
            'receivablesAFN', 'receivablesUSD',
            'payablesAFN', 'payablesUSD',
            'receivablesTotalAFN', 'receivablesTotalUSD',
            'payablesTotalAFN', 'payablesTotalUSD'
        ));
    }

    public function create()
    {
        // Optional party scope: coming from a ledger page narrows the document
        // picker to that customer/supplier's open documents. findOrFail() runs
        // through the BelongsToUser scope, so another shop's party 404s.
        $partyType = in_array(request('party_type'), ['customer', 'supplier'], true) ? request('party_type') : null;
        $partyId = null;
        $partyName = null;

        if ($partyType) {
            $party = $partyType === 'customer'
                ? Customer::findOrFail(request('party_id'))
                : Supplier::findOrFail(request('party_id'));

            $partyId = $party->id;
            $partyName = $party->name;
        }

        // The party may be a customer OR a supplier, so both must be eager-loaded.
        $orders = Order::with(['customer', 'supplier'])
            ->where('status', '!=', 'cancelled')
            ->when($partyType, fn ($q) => $q->where('person_type', $partyType)->where('person_id', $partyId))
            ->orderBy('created_at', 'desc')
            ->get();
        $this->attachOrderListBalances($orders);
        $orders = $orders->filter(function ($order) {
            return bccomp($order->remaining, '0', 2) > 0;
        })->values();

        // Purchases too — this page records payments against both orders and purchases.
        $purchases = Purchase::with(['customer', 'supplier'])
            ->where('status', '!=', 'cancelled')
            ->when($partyType, fn ($q) => $q->where('person_type', $partyType)->where('person_id', $partyId))
            ->orderBy('created_at', 'desc')
            ->get();
        $this->attachPurchaseListBalances($purchases);
        $purchases = $purchases->filter(function ($purchase) {
            return bccomp($purchase->remaining, '0', 2) > 0;
        })->values();

        $selectedType = in_array(request('type'), ['order', 'purchase'], true) ? request('type') : 'order';
        // A party-scoped visit may only hold one document type — default to it.
        if ($partyType && $orders->isEmpty() && $purchases->isNotEmpty()) {
            $selectedType = 'purchase';
        }
        $selectedOrderId = request('order_id');
        $selectedPurchaseId = request('purchase_id');

        $orderPicker = $orders->map(fn ($o) => [
            'id' => $o->id,
            'party' => $o->party?->name ?? '',
            'total' => (float) $o->total_amount,
            'remaining' => (float) $o->remaining,
            'currency' => $o->currency,
        ])->values();

        $purchasePicker = $purchases->map(fn ($p) => [
            'id' => $p->id,
            'party' => $p->party?->name ?? '',
            'total' => (float) $p->total_amount,
            'remaining' => (float) $p->remaining,
            'currency' => $p->currency,
        ])->values();

        return view('payments.create', compact('orders', 'purchases', 'selectedType', 'selectedOrderId', 'selectedPurchaseId', 'orderPicker', 'purchasePicker', 'partyType', 'partyId', 'partyName'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:order,purchase',
            'order_id' => 'exclude_unless:type,order|required|exists:orders,id',
            'purchase_id' => 'exclude_unless:type,purchase|required|exists:purchases,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'notes' => 'nullable|string|max:500',
        ]);

        $rejected = null;
        $document = null;

        // The document is read under row lock inside the transaction so two
        // concurrent payments can't both pass the remaining-balance check
        // against the same stale number and overpay the document.
        DB::transaction(function () use ($validated, &$rejected, &$document) {
            $document = $validated['type'] === 'order'
                ? Order::whereKey($validated['order_id'])->lockForUpdate()->first()
                : Purchase::whereKey($validated['purchase_id'])->lockForUpdate()->first();

            if (! $document) {
                abort(404);
            }

            if ($validated['currency'] !== $document->currency) {
                $rejected = __('messages.payment_currency_mismatch');

                return;
            }

            $remaining = $document->remaining_amount;

            if (bccomp((string) $validated['amount'], (string) $remaining, 2) > 0) {
                $symbol = $document->currency === 'USD' ? '$' : __('messages.afn');
                $rejected = __('messages.payment_exceeds_balance').' ('.__('messages.pending').': '.number_format($remaining).' '.$symbol.')';

                return;
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
        });

        if ($rejected !== null) {
            return back()->with('error', $rejected);
        }

        // Ledger pages send the shopper here with return_to=ledger; send them
        // back to the paid document's party. The party comes from the document
        // itself, never from the request, so the target can't be tampered with.
        if ($request->input('return_to') === 'ledger' && $document) {
            return redirect()->route('ledger.show', [$document->person_type, $document->person_id])
                ->with('success', __('messages.payment_created'));
        }

        return redirect()->route('payments.index')->with('success', __('messages.payment_created'));
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();

        return redirect()->route('payments.index')->with('success', __('messages.payment_deleted'));
    }

    public function customerPaymentsPage()
    {
        return $this->index(app(CashFlowService::class));
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
