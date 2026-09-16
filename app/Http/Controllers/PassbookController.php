<?php

namespace App\Http\Controllers;

use App\Models\CashbookEntry;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\PurchasePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PassbookController extends Controller
{
    private const FILTER_SOURCES = [
        'cash_in' => ['cashbook', 'payments', 'partyPayments'],
        'cash_out' => ['cashbook', 'purchasePayments', 'partyPayments', 'salary'],
        'expense' => ['expenses'],
    ];

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : null;

        // Date-only columns (transaction_date / entry_date / expense_date) may
        // hold either 'Y-m-d' or a full timestamp; an exclusive upper bound
        // keeps both forms inside the requested range on SQLite.
        $toExclusive = $to ? $to->copy()->addDay()->toDateString() : null;

        // The feed is cash-only: cashbook entries (both generations),
        // customer/supplier payments, on-account party payments, salary
        // payments and expenses. Invoice (credit) documents are not part of
        // this flow.
        $sources = $filter === 'all'
            ? ['cashbook', 'payments', 'purchasePayments', 'partyPayments', 'expenses', 'salary']
            : (self::FILTER_SOURCES[$filter] ?? []);

        $query = collect();

        // Cashbook entries (Cash In / Cash Out) and salary payments (Cash Out,
        // posted to the ledger with source 'salary' like a cashbook outflow).
        if (in_array('cashbook', $sources, true) || in_array('salary', $sources, true)) {
            $journalSources = ['cashbook_in', 'cashbook_out'];
            if (in_array('salary', $sources, true)) {
                $journalSources[] = 'salary';
            }

            $journalQuery = JournalEntry::with('ledgerEntries', 'reference')
                ->whereIn('source', $journalSources)
                ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from->toDateString()))
                ->when($to, fn ($q) => $q->where('transaction_date', '<', $toExclusive));

            if ($filter === 'cash_in') {
                $journalQuery->where('source', 'cashbook_in');
            } elseif ($filter === 'cash_out') {
                $journalQuery->whereIn('source', ['cashbook_out', 'salary']);
            }

            foreach ($journalQuery->get() as $journal) {
                $cashLine = $journal->ledgerEntries->first();
                $isIn = $journal->source === 'cashbook_in';
                $party = $journal->reference?->name;
                $notes = $journal->ledgerEntries->pluck('notes')->filter()->first();

                $query->push([
                    'date' => $journal->transaction_date,
                    'type' => $isIn ? 'cash_in' : 'cash_out',
                    'description' => trim(implode(' - ', array_filter([
                        $journal->description,
                        $party,
                        $notes,
                    ]))),
                    'amount' => $cashLine?->amount ?? 0,
                    'currency' => $journal->currency,
                    'reference' => ($journal->source === 'salary' ? 'SAL-' : 'CB-').$journal->id,
                    'icon' => $isIn ? 'cash_in' : 'cash_out',
                ]);
            }

            // Legacy cashbook rows (pre-Fix C2) live in cashbook_entries, not
            // the journal — the two generations are disjoint, so both must be
            // listed or pre-migration entries vanish from the passbook.
            $legacyQuery = CashbookEntry::query()
                ->when($from, fn ($q) => $q->where('entry_date', '>=', $from->toDateString()))
                ->when($to, fn ($q) => $q->where('entry_date', '<', $toExclusive));

            if ($filter === 'cash_in') {
                $legacyQuery->where('type', 'in');
            } elseif ($filter === 'cash_out') {
                $legacyQuery->where('type', 'out');
            }

            foreach ($legacyQuery->get() as $entry) {
                $query->push([
                    'date' => $entry->entry_date,
                    'type' => $entry->type === 'in' ? 'cash_in' : 'cash_out',
                    'description' => ($entry->type === 'in' ? __('messages.cash_income') : __('messages.cash_expense'))
                        .($entry->notes ? ' - '.$entry->notes : ''),
                    'amount' => $entry->amount,
                    'currency' => $entry->currency,
                    'reference' => 'CBL-'.$entry->id,
                    'icon' => $entry->type === 'in' ? 'cash_in' : 'cash_out',
                ]);
            }
        }

        // On-account party payments (money received / paid outside any
        // document) — real cash movement, so the passbook must show them.
        if (in_array('partyPayments', $sources, true)) {
            PartyPayment::with('person')
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->get()
                ->each(function (PartyPayment $partyPayment) use (&$query) {
                    $isIn = $partyPayment->type === 'payment_received';

                    $query->push([
                        'date' => $partyPayment->created_at,
                        'type' => $isIn ? 'cash_in' : 'cash_out',
                        'description' => ($isIn ? __('messages.payment_from') : __('messages.payment_to'))
                            .($partyPayment->person?->name ?? __('messages.unknown'))
                            .($partyPayment->notes ? ' - '.$partyPayment->notes : ''),
                        'amount' => $partyPayment->amount,
                        'currency' => $partyPayment->currency,
                        'reference' => 'PP-'.$partyPayment->id,
                        'icon' => $isIn ? 'cash_in' : 'cash_out',
                    ]);
                });
        }

        // Customer Payments (Cash In)
        if (in_array('payments', $sources, true)) {
            $payments = Payment::with('order.customer', 'order.supplier')
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->get();

            foreach ($payments as $payment) {
                $query->push([
                    'date' => $payment->created_at,
                    'type' => 'cash_in',
                    'description' => __('messages.payment_from').' '.($payment->order->party?->name ?? __('messages.unknown')),
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'reference' => 'PAY-'.$payment->id,
                    'icon' => 'cash_in',
                ]);
            }
        }

        // Supplier Payments (Cash Out)
        if (in_array('purchasePayments', $sources, true)) {
            $purchasePayments = PurchasePayment::with('purchase.customer', 'purchase.supplier')
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->get();

            foreach ($purchasePayments as $pp) {
                $query->push([
                    'date' => $pp->created_at,
                    'type' => 'cash_out',
                    'description' => __('messages.payment_to').' '.($pp->purchase->party?->name ?? __('messages.unknown')),
                    'amount' => $pp->amount,
                    'currency' => $pp->currency,
                    'reference' => 'PPAY-'.$pp->id,
                    'icon' => 'cash_out',
                ]);
            }
        }

        // Expenses
        if (in_array('expenses', $sources, true)) {
            $expenses = Expense::query()
                ->when($from, fn ($q) => $q->where('expense_date', '>=', $from->toDateString()))
                ->when($to, fn ($q) => $q->where('expense_date', '<', $toExclusive))
                ->get();

            foreach ($expenses as $expense) {
                $query->push([
                    'date' => $expense->expense_date,
                    'type' => 'expense',
                    'description' => $expense->category.($expense->notes ? ' - '.$expense->notes : ''),
                    'amount' => $expense->amount,
                    'currency' => $expense->currency,
                    'reference' => 'EXP-'.$expense->id,
                    'icon' => 'expense',
                ]);
            }
        }

        // Sort by date descending, then paginate the combined feed.
        $allTransactions = $query->sortByDesc('date')->values();

        // Totals are cash-basis: actual money received vs paid.
        $totalIn = $query->filter(fn ($i) => $i['type'] === 'cash_in' && $i['currency'] === 'AFN')->sum('amount');
        $totalInUSD = $query->filter(fn ($i) => $i['type'] === 'cash_in' && $i['currency'] === 'USD')->sum('amount');
        $totalOut = $query->filter(fn ($i) => in_array($i['type'], ['cash_out', 'expense'], true) && $i['currency'] === 'AFN')->sum('amount');
        $totalOutUSD = $query->filter(fn ($i) => in_array($i['type'], ['cash_out', 'expense'], true) && $i['currency'] === 'USD')->sum('amount');

        $transactions = $this->paginateCollection($allTransactions);

        return view('passbook.index', compact('transactions', 'filter', 'dateFrom', 'dateTo', 'totalIn', 'totalInUSD', 'totalOut', 'totalOutUSD'));
    }
}
