<?php

namespace App\Services\Billing;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for paid / pending / remaining amounts.
 *
 * Canonical rule (mirrors Order::remaining_amount / Purchase::remaining_amount):
 * only non-cancelled documents count, only same-currency payments settle a
 * document, non-cancelled returns reduce the balance, and each document's
 * remainder is clamped at zero BEFORE it is summed.
 */
class PartyBalanceService
{
    /**
     * Outstanding balance per currency for one party (customer or supplier),
     * across every order AND purchase held under the canonical
     * person_type / person_id, plus unallocated party-level payments.
     *
     * Returns ['total_documents' => int, 'total_amount_afn' => string, ...
     *          'paid_afn' => string, 'remaining_afn' => string, ...].
     */
    public function partySummary(string $personType, int $personId): array
    {
        $orderIds = Order::where('person_type', $personType)->where('person_id', $personId)
            ->where('status', '!=', 'cancelled')->pluck('id')->all();
        $purchaseIds = Purchase::where('person_type', $personType)->where('person_id', $personId)
            ->where('status', '!=', 'cancelled')->pluck('id')->all();

        $summary = [
            'total_documents' => count($orderIds) + count($purchaseIds),
        ];

        foreach (['AFN', 'USD'] as $currency) {
            $sfx = strtolower($currency);

            $billed = '0.00';
            $paid = '0.00';
            $returned = '0.00';
            $remaining = '0.00';

            if ($orderIds) {
                $billed = bcadd($billed, (string) Order::whereIn('id', $orderIds)->where('currency', $currency)->sum('total_amount'), 2);
                $returned = bcadd($returned, (string) OrderReturn::whereIn('order_id', $orderIds)->where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount'), 2);
                $paid = bcadd($paid, (string) Payment::whereIn('order_id', $orderIds)->where('currency', $currency)->sum('amount'), 2);
                $remaining = bcadd($remaining, $this->docGroupRemaining('orders', 'order_id', 'payments', 'order_returns', $orderIds, $currency), 2);
            }

            if ($purchaseIds) {
                $billed = bcadd($billed, (string) Purchase::whereIn('id', $purchaseIds)->where('currency', $currency)->sum('total_amount'), 2);
                $returned = bcadd($returned, (string) PurchaseReturn::whereIn('purchase_id', $purchaseIds)->where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount'), 2);
                $paid = bcadd($paid, (string) PurchasePayment::whereIn('purchase_id', $purchaseIds)->where('currency', $currency)->sum('amount'), 2);
                $remaining = bcadd($remaining, $this->docGroupRemaining('purchases', 'purchase_id', 'purchase_payments', 'purchase_returns', $purchaseIds, $currency), 2);
            }

            // Direct party payments recorded through the ledger (not tied to a
            // document — on-account money, or legacy rows) also settle the balance.
            $partyPaid = PartyPayment::where('person_type', $personType)->where('person_id', $personId)
                ->where('currency', $currency)
                ->where('type', $personType === 'customer' ? 'payment_received' : 'payment_made')
                ->sum('amount');
            $partyPaid = bcadd('0.00', (string) $partyPaid, 2);

            $remaining = bcsub($remaining, $partyPaid, 2);

            // On-account money beyond what the documents owe is a prepayment:
            // surface it as credit instead of letting the clamp swallow it.
            $credit = '0.00';
            if (bccomp($remaining, '0', 2) < 0) {
                $credit = bcsub('0.00', $remaining, 2);
                $remaining = '0.00';
            }

            $summary["total_amount_{$sfx}"] = $billed;
            $summary["returned_{$sfx}"] = $returned;
            $summary["paid_{$sfx}"] = bcadd($paid, $partyPaid, 2);
            $summary["remaining_{$sfx}"] = $remaining;
            $summary["credit_{$sfx}"] = $credit;
        }

        return $summary;
    }

    /**
     * Pending (still owed) amount for one party in one currency.
     */
    public function pendingAmount(string $personType, int $personId, string $currency): string
    {
        return $this->partySummary($personType, $personId)['remaining_'.strtolower($currency)];
    }

    /**
     * Shop-wide outstanding totals per party type per currency (header tiles).
     * One grouped query per document type + one party-payment read. Balances
     * are clamped PER PARTY, so one party's on-account credit can only zero
     * its own balance — never offset what another party still owes.
     */
    public function outstandingByPartyType(): array
    {
        $totals = [
            'customer' => ['AFN' => '0.00', 'USD' => '0.00'],
            'supplier' => ['AFN' => '0.00', 'USD' => '0.00'],
        ];

        // Per-document remaining (clamped at zero) rolled up per party.
        $balances = [];

        foreach ([
            ['orders', 'order_id', 'payments', 'order_returns'],
            ['purchases', 'purchase_id', 'purchase_payments', 'purchase_returns'],
        ] as [$table, $fk, $paymentTable, $returnTable]) {
            $rows = DB::table($table)
                ->selectRaw("{$table}.person_type, {$table}.person_id, {$table}.currency, MAX({$table}.total_amount - COALESCE((SELECT SUM(amount) FROM {$paymentTable} WHERE {$paymentTable}.{$fk} = {$table}.id AND {$paymentTable}.currency = {$table}.currency), 0) - COALESCE((SELECT SUM(total_amount) FROM {$returnTable} WHERE {$returnTable}.{$fk} = {$table}.id AND {$returnTable}.status != 'cancelled' AND {$returnTable}.currency = {$table}.currency), 0), 0) AS remaining")
                ->where("{$table}.user_id", Auth::id())
                ->where("{$table}.status", '!=', 'cancelled')
                ->groupBy("{$table}.id", "{$table}.person_type", "{$table}.person_id", "{$table}.currency")
                ->get();

            foreach ($rows as $row) {
                if (! isset($totals[$row->person_type][$row->currency])) {
                    continue;
                }

                $key = $row->person_type.':'.$row->person_id;
                $balances[$key]['type'] = $row->person_type;
                $balances[$key][$row->currency] = bcadd($balances[$key][$row->currency] ?? '0.00', (string) $row->remaining, 2);
            }
        }

        // On-account party payments settle the owning party's balance only,
        // mirroring partySummary() (settlement direction per party type).
        foreach (PartyPayment::get(['person_type', 'person_id', 'currency', 'type', 'amount']) as $p) {
            if (! isset($totals[$p->person_type][$p->currency])) {
                continue;
            }
            if ($p->type !== ($p->person_type === 'customer' ? 'payment_received' : 'payment_made')) {
                continue;
            }

            $key = $p->person_type.':'.$p->person_id;
            $balances[$key]['type'] = $p->person_type;
            $balances[$key][$p->currency] = bcsub($balances[$key][$p->currency] ?? '0.00', (string) $p->amount, 2);
        }

        // Clamp each party at zero, then roll up per party type.
        foreach ($balances as $party) {
            foreach (['AFN', 'USD'] as $currency) {
                $amount = $party[$currency] ?? '0.00';
                if (bccomp($amount, '0', 2) > 0) {
                    $totals[$party['type']][$currency] = bcadd($totals[$party['type']][$currency], $amount, 2);
                }
            }
        }

        return $totals;
    }

    /**
     * Per-document remaining for a set of documents in ONE grouped query,
     * mirroring the model accessors exactly. The groupBy makes the MAX()
     * deterministic — without it SQLite's bare-column aggregation returns
     * one arbitrary row.
     */
    protected function docGroupRemaining(string $table, string $fk, string $paymentTable, string $returnTable, array $ids, string $currency): string
    {
        if (! $ids) {
            return '0.00';
        }

        $sum = DB::table($table)
            ->selectRaw("MAX({$table}.total_amount - COALESCE((SELECT SUM(amount) FROM {$paymentTable} WHERE {$paymentTable}.{$fk} = {$table}.id AND {$paymentTable}.currency = {$table}.currency), 0) - COALESCE((SELECT SUM(total_amount) FROM {$returnTable} WHERE {$returnTable}.{$fk} = {$table}.id AND {$returnTable}.status != 'cancelled' AND {$returnTable}.currency = {$table}.currency), 0), 0) AS remaining")
            ->whereIn("{$table}.id", $ids)
            ->where("{$table}.currency", $currency)
            ->where("{$table}.status", '!=', 'cancelled')
            ->groupBy("{$table}.id")
            ->get()
            ->sum('remaining');

        return bcadd('0.00', (string) $sum, 2);
    }
}
