<?php

namespace App\Services\Accounting;

use App\Models\CashbookEntry;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\SalaryPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Canonical cash-flow union: every flow the app records, summed per currency.
 *
 * Sources (disjoint by construction — no double counting):
 *  - Payment / PurchasePayment: money settled against a non-cancelled order /
 *    purchase (same filter as the dashboard revenue/expense tiles).
 *  - PartyPayment: on-account money the ledger could not allocate to a document.
 *  - Cashbook, journal generation: journal_entries with source cashbook_in/out
 *    (what the live cashbook form posts since Fix C2).
 *  - Cashbook, legacy generation: rows in cashbook_entries (pre-Fix C2).
 *  - Expense / SalaryPayment: standalone outflows.
 *
 * Every page that renders a cash figure (wallet hero, dashboard, cashbook
 * tiles, passbook, daybook opening, balance sheet, API dashboard) derives it
 * from here so the numbers can never disagree.
 */
class CashFlowService
{
    /**
     * Memoized all-time computation shared by totals() / cashbookIn() /
     * cashbookOut() within one request, so pages that need several views of
     * the same money (e.g. the balance sheet's assets AND equity sides) pay
     * for the queries once.
     */
    protected ?array $allTime = null;

    /**
     * All-time cash in/out/net per currency for the current user, including
     * the cashbook subtotals (both generations) so callers can render the
     * cashbook's share without re-querying.
     *
     * @return array{AFN: array{in: float, out: float, balance: float, cashbook_in: float, cashbook_out: float}, USD: array{in: float, out: float, balance: float, cashbook_in: float, cashbook_out: float}}
     */
    public function totals(): array
    {
        $computed = $this->computeAllTime();

        $totals = [];
        foreach (['AFN', 'USD'] as $currency) {
            $totals[$currency] = [
                'in' => $computed['in'][$currency],
                'out' => $computed['out'][$currency],
                'balance' => $computed['in'][$currency] - $computed['out'][$currency],
                'cashbook_in' => $computed['cashbook_in'][$currency],
                'cashbook_out' => $computed['cashbook_out'][$currency],
            ];
        }

        return $totals;
    }

    /**
     * All-time cashbook IN (both generations) for one currency, as a
     * 2-decimal string — the balance sheet's equity-side cash income.
     */
    public function cashbookIn(string $currency): string
    {
        $computed = $this->computeAllTime();

        return number_format($computed['cashbook_in'][$currency], 2, '.', '');
    }

    /**
     * All-time cashbook OUT (both generations) for one currency, as a
     * 2-decimal string — the balance sheet's equity-side operating expense.
     */
    public function cashbookOut(string $currency): string
    {
        $computed = $this->computeAllTime();

        return number_format($computed['cashbook_out'][$currency], 2, '.', '');
    }

    /**
     * Cash balance per currency strictly BEFORE a date (a daybook's opening
     * balance). Date-only columns (transaction_date / entry_date /
     * expense_date) compare against the plain Y-m-d bound; timestamped ones
     * (created_at) exclude the whole bound day.
     *
     * @return array{AFN: float, USD: float}
     */
    public function balanceBefore(string $beforeDate): array
    {
        $in = $this->inSources($beforeDate);
        $out = $this->outSources($beforeDate);

        return [
            'AFN' => $in['AFN'] - $out['AFN'],
            'USD' => $in['USD'] - $out['USD'],
        ];
    }

    /**
     * Cashbook OUT (both generations) inside a [from, toExclusive) date
     * window for one currency — the P&L "Cash Expenses" line. Date-only
     * columns may hold either 'Y-m-d' or a full timestamp, so the window uses
     * an inclusive lower / exclusive upper bound on the date string.
     */
    public function cashbookOutBetween(string $currency, string $fromDate, string $toDateExclusive): string
    {
        $journal = (float) $this->journalCashbookBase()
            ->where('journal_entries.source', 'cashbook_out')
            ->where('journal_entries.currency', $currency)
            ->where('journal_entries.transaction_date', '>=', $fromDate)
            ->where('journal_entries.transaction_date', '<', $toDateExclusive)
            ->sum('ledger_amounts.amount');

        $legacy = (float) CashbookEntry::where('type', 'out')
            ->where('currency', $currency)
            ->where('entry_date', '>=', $fromDate)
            ->where('entry_date', '<', $toDateExclusive)
            ->sum('amount');

        return number_format($journal + $legacy, 2, '.', '');
    }

    /**
     * One memoized pass over every all-time source, per currency.
     *
     * @return array{in: array{AFN: float, USD: float}, out: array{AFN: float, USD: float}, cashbook_in: array{AFN: float, USD: float}, cashbook_out: array{AFN: float, USD: float}}
     */
    private function computeAllTime(): array
    {
        if ($this->allTime !== null) {
            return $this->allTime;
        }

        $journalIn = $this->journalCashbookBefore('cashbook_in', null);
        $legacyIn = $this->legacyCashbookBefore('in', null);
        $journalOut = $this->journalCashbookBefore('cashbook_out', null);
        $legacyOut = $this->legacyCashbookBefore('out', null);

        $in = $this->mergeCurrencies([
            $this->paymentIn(),
            $this->partyIn(),
            $journalIn,
            $legacyIn,
        ]);

        $out = $this->mergeCurrencies([
            $this->purchasePaymentOut(),
            $this->partyOut(),
            $this->expenseOut(),
            $this->salaryOut(),
            $journalOut,
            $legacyOut,
        ]);

        return $this->allTime = [
            'in' => $in,
            'out' => $out,
            'cashbook_in' => $this->mergeCurrencies([$journalIn, $legacyIn]),
            'cashbook_out' => $this->mergeCurrencies([$journalOut, $legacyOut]),
        ];
    }

    /**
     * @return array{AFN: float, USD: float}
     */
    private function inSources(?string $before): array
    {
        $maps = [
            $this->paymentIn($before),
            $this->partyIn($before),
            $this->journalCashbookBefore('cashbook_in', $before),
            $this->legacyCashbookBefore('in', $before),
        ];

        return $this->mergeCurrencies($maps);
    }

    /**
     * @return array{AFN: float, USD: float}
     */
    private function outSources(?string $before): array
    {
        $maps = [
            $this->purchasePaymentOut($before),
            $this->partyOut($before),
            $this->expenseOut($before),
            $this->salaryOut($before),
            $this->journalCashbookBefore('cashbook_out', $before),
            $this->legacyCashbookBefore('out', $before),
        ];

        return $this->mergeCurrencies($maps);
    }

    /**
     * @return array{AFN: float, USD: float}
     */
    private function paymentIn(?string $before = null): array
    {
        return $this->sumByCurrency(
            Payment::query()
                ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->when($before, fn ($q) => $q->where('created_at', '<', $before)),
        );
    }

    /**
     * @return array{AFN: float, USD: float}
     */
    private function partyIn(?string $before = null): array
    {
        return $this->sumByCurrency(
            PartyPayment::query()
                ->where('type', 'payment_received')
                ->when($before, fn ($q) => $q->where('created_at', '<', $before)),
        );
    }

    /**
     * @return array{AFN: float, USD: float}
     */
    private function purchasePaymentOut(?string $before = null): array
    {
        return $this->sumByCurrency(
            PurchasePayment::query()
                ->whereHas('purchase', fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->when($before, fn ($q) => $q->where('created_at', '<', $before)),
        );
    }

    /**
     * @return array{AFN: float, USD: float}
     */
    private function partyOut(?string $before = null): array
    {
        return $this->sumByCurrency(
            PartyPayment::query()
                ->where('type', 'payment_made')
                ->when($before, fn ($q) => $q->where('created_at', '<', $before)),
        );
    }

    /**
     * @return array{AFN: float, USD: float}
     */
    private function expenseOut(?string $before = null): array
    {
        return $this->sumByCurrency(
            Expense::query()
                ->when($before, fn ($q) => $q->where('expense_date', '<', $before)),
        );
    }

    /**
     * @return array{AFN: float, USD: float}
     */
    private function salaryOut(?string $before = null): array
    {
        return $this->sumByCurrency(
            SalaryPayment::query()
                ->whereHas('employee', fn ($q) => $q->where('user_id', Auth::id()))
                ->when($before, fn ($q) => $q->where('created_at', '<', $before)),
        );
    }

    /**
     * Grouped SUM(amount) per currency for an (already user-scoped) query.
     *
     * @return array{AFN: float, USD: float}
     */
    private function sumByCurrency($query): array
    {
        $rows = $query->groupBy('currency')
            ->selectRaw('currency, SUM(amount) as total')
            ->pluck('total', 'currency');

        return [
            'AFN' => (float) ($rows['AFN'] ?? 0),
            'USD' => (float) ($rows['USD'] ?? 0),
        ];
    }

    /**
     * Journal cashbook amounts per currency, optionally bounded to history
     * before a date. Each journal posts TWO balanced ledger lines, so a
     * journal's amount is the MAX of its lines (also robust to fixtures that
     * post a single line).
     *
     * @return array{AFN: float, USD: float}
     */
    private function journalCashbookBefore(string $source, ?string $before): array
    {
        $rows = $this->journalCashbookBase()
            ->where('journal_entries.source', $source)
            ->when($before, fn ($q) => $q->where('journal_entries.transaction_date', '<', $before))
            ->groupBy('journal_entries.currency')
            ->selectRaw('journal_entries.currency, SUM(ledger_amounts.amount) as total')
            ->pluck('total', 'journal_entries.currency');

        return [
            'AFN' => (float) ($rows['AFN'] ?? 0),
            'USD' => (float) ($rows['USD'] ?? 0),
        ];
    }

    /**
     * Journal + ledger join where each journal's amount is MAX(lines).
     */
    private function journalCashbookBase()
    {
        return JournalEntry::query()
            ->joinSub(
                DB::table('ledger_entries')
                    ->selectRaw('journal_entry_id, MAX(amount) as amount')
                    ->groupBy('journal_entry_id'),
                'ledger_amounts',
                'ledger_amounts.journal_entry_id',
                '=',
                'journal_entries.id'
            );
    }

    /**
     * Legacy cashbook rows per currency, optionally bounded to history.
     *
     * @return array{AFN: float, USD: float}
     */
    private function legacyCashbookBefore(string $type, ?string $before): array
    {
        $rows = CashbookEntry::query()
            ->where('type', $type)
            ->when($before, fn ($q) => $q->where('entry_date', '<', $before))
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount) as total')
            ->pluck('total', 'currency');

        return [
            'AFN' => (float) ($rows['AFN'] ?? 0),
            'USD' => (float) ($rows['USD'] ?? 0),
        ];
    }

    /**
     * Add several per-currency maps together.
     *
     * @param  array<int, array{AFN: float, USD: float}>  $maps
     * @return array{AFN: float, USD: float}
     */
    private function mergeCurrencies(array $maps): array
    {
        $merged = ['AFN' => 0.0, 'USD' => 0.0];
        foreach ($maps as $map) {
            $merged['AFN'] += $map['AFN'];
            $merged['USD'] += $map['USD'];
        }

        return $merged;
    }
}
