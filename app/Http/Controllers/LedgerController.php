<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\PartyPayment;
use App\Models\Setting;
use App\Services\Billing\PartyBalanceService;
use App\Services\Billing\PaymentAllocationService;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function __construct(
        private PartyBalanceService $balances,
        private PaymentAllocationService $allocator,
    ) {}

    public function index()
    {
        // Build a lightweight people list first, paginate it, then summarize only the page.
        $peopleList = Customer::orderBy('name')->get(['id', 'name', 'phone'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'type' => 'customer',
                'type_label' => __('messages.customer'),
            ])
            ->concat(
                Supplier::orderBy('name')->get(['id', 'name', 'phone'])->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'phone' => $s->phone,
                    'type' => 'supplier',
                    'type_label' => __('messages.supplier'),
                ])
            )
            ->sortBy('name')
            ->values();

        // Shop-wide outstanding totals for the header tiles via the canonical
        // balance service (same per-doc clamped math as every other page).
        $totals = $this->balances->outstandingByPartyType();

        $counts = [
            'customer' => $peopleList->where('type', 'customer')->count(),
            'supplier' => $peopleList->where('type', 'supplier')->count(),
        ];

        $people = $this->paginateCollection($peopleList);

        $people->setCollection(
            $people->getCollection()->map(function (array $person) {
                return $person + $this->summarizePerson($person['type'], $person['id']);
            })
        );

        return view('ledger.index', compact('people', 'totals', 'counts'));
    }

    /**
     * Outstanding balance per party currency, across every document the party
     * holds — the canonical math shared with orders, wallet and reports.
     */
    protected function summarizePerson(string $type, int $id): array
    {
        return $this->balances->partySummary($type, $id);
    }

    public function show($type, $id)
    {
        if ($type === 'customer') {
            $person = Customer::findOrFail($id);
            $personLabel = __('messages.customer');
        } elseif ($type === 'supplier') {
            $person = Supplier::findOrFail($id);
            $personLabel = __('messages.supplier');
        } else {
            abort(404);
        }

        // Reuse the exact summary the index shows, so list and detail agree.
        $summary = $this->summarizePerson($type, (int) $id);

        // Full payment history: document-linked payments (allocated) plus any
        // on-account ledger entries, so the ledger shows the same money that
        // settled the orders/purchases pages.
        $entries = $this->paymentHistory($type, (int) $id);

        return view('ledger.show', [
            'person' => $person,
            'personType' => $type,
            'personLabel' => $personLabel,
            'summary' => $summary,
            'entries' => $entries,
        ]);
    }

    /**
     * Merged, newest-first payment history for one party across orders,
     * purchases, returns and on-account ledger entries. Returns credit the
     * party the same direction a payment does, so they carry the same entry
     * type with a 'return' kind for distinct rendering.
     */
    protected function paymentHistory(string $type, int $id): array
    {
        $entries = [];
        $entryType = $type === 'customer' ? 'payment_received' : 'payment_made';

        $orderIds = Order::where('person_type', $type)->where('person_id', $id)
            ->where('status', '!=', 'cancelled')->pluck('id');
        foreach (Payment::whereIn('order_id', $orderIds)->get() as $payment) {
            $entries[] = (object) [
                'type' => $entryType,
                'kind' => 'payment',
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'label' => __('messages.order').' #'.$payment->order_id,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at,
            ];
        }
        foreach (OrderReturn::whereIn('order_id', $orderIds)->where('status', '!=', 'cancelled')->get() as $return) {
            $entries[] = (object) [
                'type' => $entryType,
                'kind' => 'return',
                'amount' => (string) $return->total_amount,
                'currency' => $return->currency,
                'label' => __('messages.returned').' — '.__('messages.order').' #'.$return->order_id,
                'notes' => $return->reason,
                'created_at' => $return->created_at,
            ];
        }

        $purchaseIds = Purchase::where('person_type', $type)->where('person_id', $id)
            ->where('status', '!=', 'cancelled')->pluck('id');
        foreach (PurchasePayment::whereIn('purchase_id', $purchaseIds)->get() as $payment) {
            $entries[] = (object) [
                'type' => $entryType,
                'kind' => 'payment',
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'label' => __('messages.purchase').' #'.$payment->purchase_id,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at,
            ];
        }
        foreach (PurchaseReturn::whereIn('purchase_id', $purchaseIds)->where('status', '!=', 'cancelled')->get() as $return) {
            $entries[] = (object) [
                'type' => $entryType,
                'kind' => 'return',
                'amount' => (string) $return->total_amount,
                'currency' => $return->currency,
                'label' => __('messages.returned').' — '.__('messages.purchase').' #'.$return->purchase_id,
                'notes' => $return->reason,
                'created_at' => $return->created_at,
            ];
        }

        foreach (PartyPayment::where('person_type', $type)->where('person_id', $id)->get() as $entry) {
            $entries[] = (object) [
                'type' => $entry->type,
                'kind' => 'payment',
                'amount' => (string) $entry->amount,
                'currency' => $entry->currency,
                'label' => __('messages.ledger_close'),
                'notes' => $entry->notes,
                'created_at' => $entry->created_at,
            ];
        }

        usort($entries, fn ($a, $b) => $b->created_at <=> $a->created_at);

        return $entries;
    }

    /**
     * Record a payment from the ledger page. The amount is allocated onto the
     * party's open documents (oldest first, same currency) so every page —
     * orders, purchases, wallet, dashboard — sees the money settle the same
     * documents. Anything that cannot be allocated stays on account.
     */
    public function paymentStore(Request $request, $type, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'notes' => 'nullable|string|max:500',
        ]);

        if (! in_array($type, ['customer', 'supplier'], true)) {
            abort(404);
        }

        // findOrFail() runs through the BelongsToUser global scope, so a party
        // belonging to another shop 404s instead of accepting the payment.
        $person = $type === 'customer'
            ? Customer::findOrFail($id)
            : Supplier::findOrFail($id);

        $this->allocator->allocate($type, $person->id, (string) $validated['amount'], $validated['currency'], $validated['notes'] ?? null);

        $personLabel = $type === 'customer' ? __('messages.customer') : __('messages.supplier');
        return redirect()->route('ledger.show', [$type, $person->id])
            ->with('success', __('messages.payment_created_for') . ' ' . $personLabel . '!');
    }

    public function downloadPdf($type, $id)
    {
        if ($type === 'customer') {
            $person = Customer::findOrFail($id);
            $personType = 'customer';
            $personLabel = __('messages.customer');
            $typeLabel = __('messages.customer_ledger');
        } elseif ($type === 'supplier') {
            $person = Supplier::findOrFail($id);
            $personType = 'supplier';
            $personLabel = __('messages.supplier');
            $typeLabel = __('messages.supplier_ledger');
        } else {
            abort(404);
        }

        $personSide = $type === 'customer' ? 'payment_received' : 'payment_made';
        $orders = Order::with('payments')
            ->where('person_type', $personType)
            ->where('person_id', $id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'asc')
            ->get();

        $purchases = Purchase::with('purchasePayments')
            ->where('person_type', $personType)
            ->where('person_id', $id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'asc')
            ->get();

        $orderReturns = OrderReturn::whereIn('order_id', $orders->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'asc')
            ->get();

        $purchaseReturns = PurchaseReturn::whereIn('purchase_id', $purchases->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'asc')
            ->get();

        $ledgerEntries = PartyPayment::where('person_type', $personType)
            ->where('person_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        // Canonical totals so the PDF summary agrees with the ledger screen.
        $summary = $this->balances->partySummary($personType, (int) $id);
        $totalOrdersAFN = (float) $summary['total_amount_afn'];
        $totalOrdersUSD = (float) $summary['total_amount_usd'];
        $totalReturnedAFN = (float) $summary['returned_afn'];
        $totalReturnedUSD = (float) $summary['returned_usd'];
        $totalPaidAFN = (float) $summary['paid_afn'];
        $totalPaidUSD = (float) $summary['paid_usd'];
        $remainingAFN = (float) $summary['remaining_afn'];
        $remainingUSD = (float) $summary['remaining_usd'];

        $transactions = [];

        foreach ($orders as $order) {
            $transactions[] = [
                'date' => $order->created_at,
                'description' => __('messages.order') . ' #' . $order->id,
                'ref' => 'ORD-' . $order->id,
                'amount' => (float) $order->total_amount,
                'currency' => $order->currency,
                'is_positive' => true,
            ];

            foreach ($order->payments as $payment) {
                $transactions[] = [
                    'date' => $payment->created_at,
                    'description' => __('messages.paid_short') . ' — ' . __('messages.order') . ' #' . $order->id,
                    'ref' => 'PAY-' . $payment->id,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'is_positive' => false,
                ];
            }
        }

        foreach ($purchases as $purchase) {
            $transactions[] = [
                'date' => $purchase->created_at,
                'description' => __('messages.purchase') . ' #' . $purchase->id,
                'ref' => 'PUR-' . $purchase->id,
                'amount' => (float) $purchase->total_amount,
                'currency' => $purchase->currency,
                'is_positive' => true,
            ];

            foreach ($purchase->purchasePayments as $purchasePayment) {
                $transactions[] = [
                    'date' => $purchasePayment->created_at,
                    'description' => __('messages.paid_short') . ' — ' . __('messages.purchase') . ' #' . $purchase->id,
                    'ref' => 'PPAY-' . $purchasePayment->id,
                    'amount' => (float) $purchasePayment->amount,
                    'currency' => $purchasePayment->currency,
                    'is_positive' => false,
                ];
            }
        }

        // Returns credit the party (reduce what is owed on that document).
        foreach ($orderReturns as $return) {
            $transactions[] = [
                'date' => $return->created_at,
                'description' => __('messages.returned') . ' — ' . __('messages.order') . ' #' . $return->order_id,
                'ref' => 'RET-' . $return->id,
                'amount' => (float) $return->total_amount,
                'currency' => $return->currency,
                'is_positive' => false,
            ];
        }
        foreach ($purchaseReturns as $return) {
            $transactions[] = [
                'date' => $return->created_at,
                'description' => __('messages.returned') . ' — ' . __('messages.purchase') . ' #' . $return->purchase_id,
                'ref' => 'PRET-' . $return->id,
                'amount' => (float) $return->total_amount,
                'currency' => $return->currency,
                'is_positive' => false,
            ];
        }

        foreach ($ledgerEntries as $entry) {
            $description = $entry->notes ?? ($entry->type === 'payment_received' ? __('messages.receipt_amount') : __('messages.paid_short'));
            $transactions[] = [
                'date' => $entry->created_at,
                'description' => $description,
                'ref' => 'LE-' . $entry->id,
                'amount' => (float) $entry->amount,
                'currency' => $entry->currency,
                // A receipt from a customer (payment_made from a supplier) credits
                // their balance; the opposite direction adds to it.
                'is_positive' => $entry->type !== $personSide,
            ];
        }

        usort($transactions, fn ($a, $b) => $a['date'] <=> $b['date']);

        $balances = ['AFN' => 0.0, 'USD' => 0.0];
        foreach ($transactions as &$tx) {
            $balances[$tx['currency']] += $tx['is_positive'] ? $tx['amount'] : -$tx['amount'];
            $tx['balance'] = $balances[$tx['currency']];
        }
        unset($tx);

        $openingBalanceAFN = 0;
        $openingBalanceUSD = 0;

        $totalDrAFN = 0;
        $totalCrAFN = 0;
        $totalDrUSD = 0;
        $totalCrUSD = 0;
        foreach ($transactions as $tx) {
            if ($tx['currency'] === 'AFN') {
                $tx['is_positive'] ? $totalDrAFN += $tx['amount'] : $totalCrAFN += $tx['amount'];
            } else {
                $tx['is_positive'] ? $totalDrUSD += $tx['amount'] : $totalCrUSD += $tx['amount'];
            }
        }

        // Credit (on-account money beyond what the documents owe) makes the
        // closing balance negative so it agrees with the running balance.
        $creditAFN = (float) $summary['credit_afn'];
        $creditUSD = (float) $summary['credit_usd'];
        $closingBalanceAFN = $remainingAFN - $creditAFN;
        $closingBalanceUSD = $remainingUSD - $creditUSD;

        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
            'email' => Setting::get('company_email', ''),
        ];

        return view('ledger.pdf', compact(
            'company', 'person', 'personType', 'personLabel', 'typeLabel',
            'transactions', 'openingBalanceAFN', 'openingBalanceUSD',
            'closingBalanceAFN', 'closingBalanceUSD',
            'totalOrdersAFN', 'totalOrdersUSD',
            'totalReturnedAFN', 'totalReturnedUSD',
            'totalPaidAFN', 'totalPaidUSD',
            'remainingAFN', 'remainingUSD',
            'creditAFN', 'creditUSD',
            'totalDrAFN', 'totalCrAFN', 'totalDrUSD', 'totalCrUSD'
        ));
    }
}
