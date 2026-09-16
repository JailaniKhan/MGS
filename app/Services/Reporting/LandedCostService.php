<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\DB;

/**
 * Weighted average purchase cost per product per currency, landed-cost
 * inclusive: purchase lines are uplifted by their purchase's linked
 * expenses, allocated across the currency pool by line value.
 *
 * Because line-value allocation is proportional, the landed average
 * collapses to a closed form:
 *   landed_avg = base_avg × (1 + L / V)
 * where base_avg = Σ(unit_price × qty) / Σ(qty) for the product,
 *       L = expenses linked to non-cancelled purchases in the currency,
 *       V = Σ(unit_price × qty) over ALL lines in the currency pool.
 *
 * Worked example: A (10 × 100) + B (1 × 100), freight 110 → V=1100, L=110,
 * base 100 for both → landed 110 per unit for both A and B.
 *
 * Products with no purchase lines in a currency fall back to their own pool
 * price (price / price_usd) as the cost basis — the documented proxy for
 * opening stock entered through the product form (mirrors what
 * purchases:backfill-opening-stock records).
 */
class LandedCostService
{
    /**
     * @return array<int, array<string, string>> [product_id => ['AFN' => '20.50', 'USD' => '0.26']]
     */
    public function weightedAverageCost(int $userId, ?string $currency = null): array
    {
        $poolMultiplier = DB::table('expenses')
            ->join('purchases', 'expenses.purchase_id', '=', 'purchases.id')
            ->where('purchases.user_id', $userId)
            ->where('purchases.status', '!=', 'cancelled')
            ->when($currency !== null, fn ($q) => $q->where('purchases.currency', $currency))
            ->select('purchases.currency', DB::raw('COALESCE(SUM(expenses.amount), 0) as linked'))
            ->groupBy('purchases.currency')
            ->pluck('linked', 'currency');

        $poolValue = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.user_id', $userId)
            ->where('purchases.status', '!=', 'cancelled')
            ->when($currency !== null, fn ($q) => $q->where('purchases.currency', $currency))
            ->select('purchases.currency', DB::raw('SUM(purchase_items.unit_price * purchase_items.quantity) as value'))
            ->groupBy('purchases.currency')
            ->pluck('value', 'currency');

        $rows = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.user_id', $userId)
            ->where('purchases.status', '!=', 'cancelled')
            ->when($currency !== null, fn ($q) => $q->where('purchases.currency', $currency))
            ->select(
                'purchase_items.product_id',
                'purchases.currency',
                DB::raw('SUM(purchase_items.unit_price * purchase_items.quantity) * 1.0 / NULLIF(SUM(purchase_items.quantity), 0) as avg_cost')
            )
            ->groupBy('purchase_items.product_id', 'purchases.currency')
            ->get();

        $cost = [];
        foreach ($rows as $row) {
            if ($row->avg_cost === null) {
                continue;
            }

            $linked = (float) ($poolMultiplier[$row->currency] ?? 0);
            $value = (float) ($poolValue[$row->currency] ?? 0);
            $multiplier = $value > 0 ? 1 + ($linked / $value) : 1;

            $cost[$row->product_id][$row->currency] = number_format((float) $row->avg_cost * $multiplier, 2, '.', '');
        }

        // Opening-stock fallback: products with NO purchase lines in a
        // currency inherit their own pool price as the cost basis (price for
        // AFN, price_usd for USD). This is the same proxy the
        // purchases:backfill-opening-stock command would record, so both
        // paths agree. Purchases always win — never blended. A zero/absent
        // pool price keeps the "no cost data" flag. The landed multiplier
        // applies, matching what a backfilled line would absorb.
        $buckets = $currency !== null ? [$currency] : ['AFN', 'USD'];
        $proxies = DB::table('products')
            ->where('user_id', $userId)
            ->get(['id', 'price', 'price_usd']);

        foreach ($proxies as $product) {
            foreach ($buckets as $bucket) {
                if (isset($cost[$product->id][$bucket])) {
                    continue;
                }

                $proxy = $bucket === 'USD' ? $product->price_usd : $product->price;
                if ($proxy === null || (float) $proxy <= 0) {
                    continue;
                }

                $linked = (float) ($poolMultiplier[$bucket] ?? 0);
                $value = (float) ($poolValue[$bucket] ?? 0);
                $multiplier = $value > 0 ? 1 + ($linked / $value) : 1;

                $cost[$product->id][$bucket] = number_format((float) $proxy * $multiplier, 2, '.', '');
            }
        }

        return $cost;
    }
}
