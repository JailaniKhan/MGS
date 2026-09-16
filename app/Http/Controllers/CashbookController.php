<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AddsListBalances;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Accounting\CashFlowService;
use App\Services\Accounting\TransactionService;
use App\Services\Billing\BillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashbookController extends Controller
{
    use AddsListBalances;

    public function index(CashFlowService $cashFlow)
    {
        $journals = JournalEntry::query()
            ->whereIn('source', ['cashbook_in', 'cashbook_out'])
            ->with(['reference', 'ledgerEntries'])
            ->latest('transaction_date')
            ->get();

        // `journal_entries` has no `type`/`total_amount` columns; the cashbook
        // amount and direction live in `ledger_entries`. Derive them here so the
        // view can render the correct sign and amount for every entry.
        $journals->transform(function (JournalEntry $journal) {
            $cashLine = $journal->ledgerEntries->first();

            $journal->setAttribute('type', $journal->source === 'cashbook_in' ? 'in' : 'out');
            $journal->setAttribute('total_amount', $cashLine ? (float) $cashLine->amount : 0);

            return $journal;
        });

        // Group the cashbook entries by the person they reference so the page
        // can show one clickable row per customer/supplier.
        $people = [];

        foreach ($journals as $journal) {
            if (! $journal->reference_type || ! $journal->reference) {
                continue;
            }

            $key = $journal->reference_type.'-'.$journal->reference->id;

            if (! isset($people[$key])) {
                $people[$key] = [
                    'id' => $journal->reference->id,
                    'name' => $journal->reference->name,
                    'type' => $journal->reference_type,
                    'in' => ['AFN' => 0.0, 'USD' => 0.0],
                    'out' => ['AFN' => 0.0, 'USD' => 0.0],
                    'count' => 0,
                ];
            }

            $people[$key]['count']++;

            if ($journal->type === 'in') {
                $people[$key]['in'][$journal->currency] += $journal->total_amount;
            } else {
                $people[$key]['out'][$journal->currency] += $journal->total_amount;
            }
        }

        // Closing balance per person per currency — the same figure the person
        // statement's closing bar shows (cashbook in − [cashbook out + pending
        // orders + pending purchases], all in the person's own currencies).
        // Computed for EVERY person on the account (not just the current
        // pagination page) so the hero totals are page-independent; the
        // paginated rows below then pick their figure out of the same map.
        $closing = $this->closingBalancesForPeople(
            collect($people)->map(fn ($p) => ['id' => $p['id'], 'type' => $p['type']])->values()->all()
        );

        $totalClosingAFN = '0.00';
        $totalClosingUSD = '0.00';
        foreach ($people as $key => $person) {
            $person['closing'] = $closing[$key] ?? ['AFN' => '0.00', 'USD' => '0.00'];
            $totalClosingAFN = bcadd($totalClosingAFN, (string) $person['closing']['AFN'], 2);
            $totalClosingUSD = bcadd($totalClosingUSD, (string) $person['closing']['USD'], 2);
            $people[$key] = $person;
        }

        // Unlinked cashbook entries (no person reference) belong to the shop
        // itself; their net adds to the hero totals so every recorded amount
        // stays visible on this page. Signs follow the closing formula:
        // 'in' adds, 'out' subtracts.
        foreach ($journals as $journal) {
            if ($journal->reference_type || $journal->reference) {
                continue;
            }

            if ($journal->type === 'in') {
                if ($journal->currency === 'USD') {
                    $totalClosingUSD = bcadd($totalClosingUSD, (string) $journal->total_amount, 2);
                } else {
                    $totalClosingAFN = bcadd($totalClosingAFN, (string) $journal->total_amount, 2);
                }
            } else {
                if ($journal->currency === 'USD') {
                    $totalClosingUSD = bcsub($totalClosingUSD, (string) $journal->total_amount, 2);
                } else {
                    $totalClosingAFN = bcsub($totalClosingAFN, (string) $journal->total_amount, 2);
                }
            }
        }

        $people = $this->paginateCollection(
            collect(array_values($people))->sortBy('name')->values()
        );

        // Cash tiles: the canonical cash-flow union (payments, ledger
        // payments, both cashbook generations, expenses, salaries) — the
        // same figure the wallet hero and the balance sheet show.
        $totals = $cashFlow->totals();
        $cashAFN = number_format($totals['AFN']['balance'], 2, '.', '');
        $cashUSD = number_format($totals['USD']['balance'], 2, '.', '');

        return view('cashbook.index', [
            'people' => $people,
            'cashAFN' => $cashAFN,
            'cashUSD' => $cashUSD,
            'totalClosingAFN' => $totalClosingAFN,
            'totalClosingUSD' => $totalClosingUSD,
            'cashUnbalanced' => bccomp($cashAFN, '0', 2) === -1 || bccomp($cashUSD, '0', 2) === -1,
        ]);
    }

    /**
     * Closing balance per person per currency: cashbook in − (cashbook out +
     * pending order remaining + pending purchase remaining) — the in side
     * minus the out side. Negative = net owed out (red on the statement).
     * Mirrors the statement page's closing computation, so the index rows and
     * the statement closing bar can never disagree.
     *
     * @param  array<int, array{id: int, type: string}>  $people
     * @return array<string, array{AFN: string, USD: string}>
     */
    private function closingBalancesForPeople(array $people): array
    {
        if (empty($people)) {
            return [];
        }

        $closing = [];

        foreach ($people as $person) {
            $closing[$person['type'].'-'.$person['id']] = ['AFN' => '0.00', 'USD' => '0.00'];
        }

        // Cashbook in/out per person+currency. Each journal posts TWO balanced
        // ledger lines (debit + credit, equal amounts), so a journal's amount
        // is the MAX of its lines (matching the statement page's
        // ledgerEntries->first() read); the person total is then the SUM of
        // those per-journal amounts. 'in' adds to the closing (money
        // received), 'out' subtracts (money paid out).
        $cashRows = JournalEntry::query()
            ->whereIn('source', ['cashbook_in', 'cashbook_out'])
            ->whereIn('reference_type', array_unique(array_column($people, 'type')))
            ->selectRaw('reference_type, reference_id, currency, source, SUM(ledger_amounts.amount) as total')
            ->joinSub(
                DB::table('ledger_entries')->selectRaw('journal_entry_id, MAX(amount) as amount')->groupBy('journal_entry_id'),
                'ledger_amounts', 'ledger_amounts.journal_entry_id', '=', 'journal_entries.id'
            )
            ->groupBy('reference_type', 'reference_id', 'currency', 'source')
            ->get();

        foreach ($cashRows as $row) {
            $key = $row->reference_type.'-'.$row->reference_id;
            if (! isset($closing[$key])) {
                continue;
            }

            $closing[$key][$row->currency] = $row->source === 'cashbook_in'
                ? bcadd($closing[$key][$row->currency], (string) $row->total, 2)
                : bcsub($closing[$key][$row->currency], (string) $row->total, 2);
        }

        // Pending orders AND purchases per person+currency — both weigh on
        // the out side, subtracting from the closing.
        $this->applyDocumentClosing($closing, 'order', 'orders', array_column($people, 'id'));
        $this->applyDocumentClosing($closing, 'purchase', 'purchases', array_column($people, 'id'));

        return $closing;
    }

    /**
     * Subtract non-cancelled documents' remaining balances from the closing
     * map: both orders and purchases are part of the out side (pending amounts
     * still to settle). Per-document remaining is computed in a subquery
     * (payments and returns only offset in the document's own currency), then
     * summed per person.
     *
     * @param  array<string, array{AFN: string, USD: string}>  $closing
     */
    private function applyDocumentClosing(array &$closing, string $kind, string $table, array $personIds): void
    {
        $paymentTable = $kind === 'order' ? 'payments' : 'purchase_payments';
        $returnTable = $kind === 'order' ? 'order_returns' : 'purchase_returns';
        $fk = $kind === 'order' ? 'order_id' : 'purchase_id';

        $perDocument = DB::table($table.' as d')
            // The currency match lives in the ON clause: with it in the SELECT
            // (CASE WHEN), a foreign-currency payment/return row fans the join
            // and SQLite's scalar MAX(expr, 0) picks an arbitrary fan row's
            // value — silently ignoring the same-currency offset. Filtering in
            // ON leaves at most one row per document, making MAX(expr, 0) an
            // exact per-document clamp.
            ->leftJoinSub(
                DB::table($paymentTable)->selectRaw($fk.', currency, SUM(amount) as paid')->groupBy($fk, 'currency'),
                'p', fn ($join) => $join->on('p.'.$fk, '=', 'd.id')->whereColumn('p.currency', 'd.currency')
            )
            ->leftJoinSub(
                DB::table($returnTable)
                    ->where('status', '!=', 'cancelled')
                    ->selectRaw($fk.', currency, SUM(total_amount) as returned')->groupBy($fk, 'currency'),
                'r', fn ($join) => $join->on('r.'.$fk, '=', 'd.id')->whereColumn('r.currency', 'd.currency')
            )
            ->where('d.status', '!=', 'cancelled')
            ->whereIn('d.person_id', $personIds)
            ->groupBy('d.id', 'd.person_type', 'd.person_id', 'd.currency')
            ->selectRaw('d.person_type, d.person_id, d.currency,
                MAX(d.total_amount - COALESCE(p.paid, 0) - COALESCE(r.returned, 0), 0) as remaining');

        $rows = DB::query()->fromSub($perDocument, 'per_doc')
            ->groupBy('person_type', 'person_id', 'currency')
            ->selectRaw('person_type, person_id, currency, SUM(remaining) as remaining')
            ->get();

        foreach ($rows as $row) {
            $key = $row->person_type.'-'.$row->person_id;
            if (! isset($closing[$key])) {
                continue;
            }

            $closing[$key][$row->currency] = bcsub($closing[$key][$row->currency], (string) $row->remaining, 2);
        }
    }

    public function person(Request $request, $type, $id)
    {
        return view('cashbook.person', $this->personData($type, $id, $request->get('currency')));
    }

    private function personData($type, $id, ?string $currency = null)
    {
        abort_if(! in_array($type, ['customer', 'supplier'], true), 404);

        if ($type === 'customer') {
            $person = Customer::findOrFail($id);
        } else {
            $person = Supplier::findOrFail($id);
        }

        $cashbook = JournalEntry::query()
            ->whereIn('source', ['cashbook_in', 'cashbook_out'])
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->with(['ledgerEntries'])
            ->latest('transaction_date')
            ->get()
            ->transform(function (JournalEntry $journal) {
                $cashLine = $journal->ledgerEntries->first();

                $journal->setAttribute('type', $journal->source === 'cashbook_in' ? 'in' : 'out');
                $journal->setAttribute('total_amount', $cashLine ? (float) $cashLine->amount : 0);

                return $journal;
            });

        // Orders/purchases are linked to a person via person_type / person_id
        // (a customer sells, a supplier can both buy and be sold to). Load them
        // by that linkage rather than the legacy customer_id relation. Cancelled
        // documents owe nothing, so they must not count as outstanding.
        $orders = Order::where('person_type', $type)
            ->where('person_id', $id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->get();

        $this->attachOrderListBalances($orders);
        $orders = $orders->filter(fn ($order) => bccomp($order->remaining, '0', 2) > 0);

        $purchases = Purchase::where('person_type', $type)
            ->where('person_id', $id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->get();

        $this->attachPurchaseListBalances($purchases);
        $purchases = $purchases->filter(fn ($purchase) => bccomp($purchase->remaining, '0', 2) > 0);

        // Build one combined, chronologically ordered list of everything tied to
        // this person. Cashbook entries drive the running balance and the In/
        // Out/Net totals; orders and purchases are context rows — they render
        // as 'out' lines but never move the balance, so the statement's
        // closing compares the in side against the out side (cashbook out +
        // pending documents) and reads red with '-' when the out side wins.
        $transactions = collect();

        foreach ($cashbook as $entry) {
            $transactions->push((object) [
                'kind' => 'cashbook',
                'direction' => $entry->type,
                'label' => $entry->description,
                'date' => $entry->transaction_date,
                // Monotonic tiebreak for same-day lines: journal id is a
                // perfect creation sequence (timestamps can tie within a
                // second). Statement accumulation relies on this order.
                'sort_at' => $entry->transaction_date->format('Y-m-d ').str_pad((string) $entry->id, 12, '0', STR_PAD_LEFT),
                'amount' => (string) $entry->total_amount,
                'currency' => $entry->currency,
                'notes' => $entry->notes ?? '',
            ]);
        }

        foreach ($orders as $order) {
            $transactions->push((object) [
                'kind' => 'order',
                'id' => $order->id,
                'direction' => 'out',
                'label' => __('messages.order').' #'.$order->id,
                'date' => $order->created_at,
                'sort_at' => $order->created_at->format('Y-m-d H:i:s'),
                'amount' => (string) $order->remaining,
                'currency' => $order->currency,
                'notes' => ucfirst($order->list_status ?? ''),
            ]);
        }

        foreach ($purchases as $purchase) {
            $transactions->push((object) [
                'kind' => 'purchase',
                'id' => $purchase->id,
                'direction' => 'out',
                'label' => __('messages.purchase').' #'.$purchase->id,
                'date' => $purchase->created_at,
                'sort_at' => $purchase->created_at->format('Y-m-d H:i:s'),
                'amount' => (string) $purchase->remaining,
                'currency' => $purchase->currency,
                'notes' => ucfirst($purchase->list_status ?? ''),
            ]);
        }

        // Statement lines carry a running balance: every cashbook 'in' ADDS,
        // every cashbook 'out' SUBTRACTS (opening balance 0 — the statement
        // starts at the first recorded line). Orders and purchases are
        // context rows: they keep the current balance as-is. The closing bar
        // applies the same idea to the document side — when the out side
        // (cashbook out + pending documents) outweighs the in side, the party
        // is net owed out and the closing reads red with '-'.
        // Accumulate oldest → newest per currency, then present newest-first.
        // Same accumulate-then-reverse pattern as the daybook. Within one day,
        // lines keep their natural insertion order (journal creation order,
        // oldest document first) so the balance reads like a real statement
        // even when timestamps tie.
        $balances = ['AFN' => '0.00', 'USD' => '0.00'];
        $ordered = $transactions
            ->sortBy([['sort_at', 'asc'], ['date', 'asc']])
            ->values()
            ->map(function ($tx) use (&$balances) {
                if ($tx->kind === 'cashbook') {
                    $balances[$tx->currency] = $tx->direction === 'in'
                        ? bcadd($balances[$tx->currency], (string) $tx->amount, 2)
                        : bcsub($balances[$tx->currency], (string) $tx->amount, 2);
                }

                $tx->balance = $balances[$tx->currency];

                return $tx;
            });

        // Closing per currency = in side − out side, where the out side
        // includes the pending documents' remainings:
        //   closing = cashbook in + 0 − (cashbook out + Σ order.remaining
        //            + Σ purchase.remaining)
        // When the out side outweighs the in side the closing is negative —
        // net owed out — and the statement renders it red with '-'.
        $closingBalances = $balances;
        foreach ($orders as $order) {
            $closingBalances[$order->currency] = bcsub($closingBalances[$order->currency], (string) $order->remaining, 2);
        }
        foreach ($purchases as $purchase) {
            $closingBalances[$purchase->currency] = bcsub($closingBalances[$purchase->currency], (string) $purchase->remaining, 2);
        }
        $transactions = $ordered->sortByDesc('date')->values();

        // Selected currency: explicit ?currency= param, else AFN if it has any
        // activity, else the other currency. AFN is the shop's primary
        // currency, so it wins the default; the segmented control switches.
        $availableCurrencies = $transactions->pluck('currency')->unique()->values();
        $selectedCurrency = in_array($currency, ['AFN', 'USD'], true)
            ? $currency
            : ($availableCurrencies->contains('AFN') ? 'AFN' : ($availableCurrencies->first() ?? 'AFN'));
        $visibleTransactions = $transactions->where('currency', $selectedCurrency)->values();

        $totals = [];
        $orderTotals = [];
        $purchaseTotals = [];
        foreach (['AFN', 'USD'] as $currency) {
            $in = $cashbook->where('currency', $currency)->where('type', 'in')->sum('total_amount');
            $out = $cashbook->where('currency', $currency)->where('type', 'out')->sum('total_amount');
            $totals[$currency] = [
                'in' => (float) $in,
                'out' => (float) $out,
                'net' => (float) $in - (float) $out,
            ];
            $orderTotals[$currency] = (float) $orders->where('currency', $currency)->sum('remaining');
            $purchaseTotals[$currency] = (float) $purchases->where('currency', $currency)->sum('remaining');
        }

        return [
            'person' => $person,
            'personType' => $type,
            'transactions' => $visibleTransactions,
            'allTransactions' => $transactions,
            'availableCurrencies' => $availableCurrencies,
            'selectedCurrency' => $selectedCurrency,
            'closingBalances' => $closingBalances,
            'totals' => $totals,
            'orderTotals' => $orderTotals,
            'purchaseTotals' => $purchaseTotals,
        ];
    }

    public function sendStatement($type, $id, BillService $bills)
    {
        $data = $this->personData($type, $id);

        $bill = $bills->personStatement(
            $data['personType'],
            $data['person'],
            $data['totals'],
            $data['transactions'],
        );

        $result = $bills->send(
            $data['person'],
            $bill['phone'],
            $bill['message'],
            $bill['amount'],
            $bill['currency'],
        );

        $message = $result['ok']
            ? __('messages.bill_sent', ['phone' => $bill['phone']])
            : $result['error'];

        return back()->with($result['ok'] ? 'success' : 'error', $message);
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        $personOptions = collect($customers)
            ->map(fn ($customer) => [
                'value' => 'customer:'.$customer->id,
                'label' => $customer->name,
                'sublabel' => $customer->phone,
                'group' => __('messages.customers'),
            ])
            ->concat($suppliers->map(fn ($supplier) => [
                'value' => 'supplier:'.$supplier->id,
                'label' => $supplier->name,
                'sublabel' => $supplier->phone,
                'group' => __('messages.suppliers'),
            ]))
            ->values()
            ->all();

        return view('cashbook.create', compact('personOptions'));
    }

    public function store(Request $request, TransactionService $transactionService)
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'entry_date' => 'required|date',
            'person' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $meta = [
            'description' => $validated['type'] === 'in' ? __('messages.cash_income') : __('messages.cash_expense'),
            'transaction_date' => $validated['entry_date'],
            'notes' => $validated['notes'] ?? null,
        ];

        if (! empty($validated['person'])) {
            [$personType, $personId] = array_pad(explode(':', $validated['person'], 2), 2, null);

            $personExists = in_array($personType, ['customer', 'supplier'], true)
                && ctype_digit((string) $personId)
                && ($personType === 'customer'
                    ? Customer::whereKey((int) $personId)->exists()
                    : Supplier::whereKey((int) $personId)->exists());

            if (! $personExists) {
                throw ValidationException::withMessages([
                    'person' => __('messages.select_valid_person'),
                ]);
            }

            $meta['reference_type'] = $personType;
            $meta['reference_id'] = (int) $personId;
        }

        $transactionService->postCashbookEntry(
            Auth::user(),
            $validated['type'],
            number_format((float) $validated['amount'], 2, '.', ''),
            $validated['currency'],
            $meta
        );

        return redirect()->route('cashbook.index')->with('success', __('messages.cashbook_recorded'));
    }
}
