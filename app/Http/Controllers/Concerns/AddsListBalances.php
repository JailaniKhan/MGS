<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Compute the remaining balance + display status for index-list rows in ONE
 * grouped query per document type instead of the model accessors' per-row
 * queries (the accessors each run a payments SUM + a returns SUM, so a
 * 20-row page used ~160 queries; this uses 2).
 *
 * Mirrors Order::remaining_amount / Purchase::remaining_amount exactly:
 * only same-currency payments settle a document, non-cancelled returns
 * reduce the balance, and the result is clamped at zero.
 */
trait AddsListBalances
{
    protected function attachOrderListBalances(EloquentCollection $orders): void
    {
        if ($orders->isEmpty()) {
            return;
        }

        $rows = DB::table('orders as o')
            ->leftJoinSub(
                DB::table('payments')->selectRaw('order_id, currency, SUM(amount) as paid')->groupBy('order_id', 'currency'),
                'p', 'p.order_id', '=', 'o.id'
            )
            ->leftJoinSub(
                DB::table('order_returns')
                    ->where('status', '!=', 'cancelled')
                    ->selectRaw('order_id, currency, SUM(total_amount) as returned')
                    ->groupBy('order_id', 'currency'),
                'r', 'r.order_id', '=', 'o.id'
            )
            ->whereIn('o.id', $orders->pluck('id'))
            ->groupBy('o.id')
            ->selectRaw('o.id, o.total_amount - COALESCE(SUM(CASE WHEN p.currency = o.currency THEN p.paid END), 0) - COALESCE(SUM(CASE WHEN r.currency = o.currency THEN r.returned END), 0) as remaining')
            ->pluck('remaining', 'id');

        foreach ($orders as $order) {
            $this->applyBalanceAttributes($order, (string) ($rows[$order->id] ?? '0.00'));
        }
    }

    protected function attachPurchaseListBalances(EloquentCollection $purchases): void
    {
        if ($purchases->isEmpty()) {
            return;
        }

        $rows = DB::table('purchases as o')
            ->leftJoinSub(
                DB::table('purchase_payments')->selectRaw('purchase_id, currency, SUM(amount) as paid')->groupBy('purchase_id', 'currency'),
                'p', 'p.purchase_id', '=', 'o.id'
            )
            ->leftJoinSub(
                DB::table('purchase_returns')
                    ->where('status', '!=', 'cancelled')
                    ->selectRaw('purchase_id, currency, SUM(total_amount) as returned')
                    ->groupBy('purchase_id', 'currency'),
                'r', 'r.purchase_id', '=', 'o.id'
            )
            ->whereIn('o.id', $purchases->pluck('id'))
            ->groupBy('o.id')
            ->selectRaw('o.id, o.total_amount - COALESCE(SUM(CASE WHEN p.currency = o.currency THEN p.paid END), 0) - COALESCE(SUM(CASE WHEN r.currency = o.currency THEN r.returned END), 0) as remaining')
            ->pluck('remaining', 'id');

        foreach ($purchases as $purchase) {
            $this->applyBalanceAttributes($purchase, (string) ($rows[$purchase->id] ?? '0.00'));
        }
    }

    /**
     * Attach `remaining` (clamped at zero) and `list_status` (cancelled /
     * paid / the raw status) as plain attributes so the view never touches
     * the querying accessors.
     */
    protected function applyBalanceAttributes($model, string $remaining): void
    {
        $remaining = bccomp($remaining, '0', 2) >= 0 ? bcadd($remaining, '0', 2) : '0.00';

        $model->setAttribute('remaining', $remaining);
        $model->setAttribute(
            'list_status',
            $model->status === 'cancelled' ? 'cancelled' : (bccomp($remaining, '0', 2) <= 0 ? 'paid' : $model->status)
        );
    }
}
