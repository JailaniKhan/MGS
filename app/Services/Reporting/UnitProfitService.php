<?php

namespace App\Services\Reporting;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Per-unit profit/loss for sales in a period.
 *
 * Cost basis: landed-inclusive weighted average purchase price per product
 * PER CURRENCY (LandedCostService — linked freight etc. uplift the basis).
 * A sale is only matched against cost in its own currency — a product
 * bought in AFN and sold in USD has no comparable cost and is flagged,
 * never converted.
 */
class UnitProfitService
{
    public function __construct(private LandedCostService $landedCost) {}

    /**
     * @param  string|null  $currency  Restrict everything to one currency
     *                                 (e.g. 'USD'); null keeps the default
     *                                 dual-currency AFN/USD buckets.
     * @return array{
     *     totals: array<string, array{revenue: string, cost: string, profit: string, qty: int, missing_cost_lines: int}>,
     *     by_product: Collection,
     *     sale_lines: Collection,
     * }
     */
    public function forPeriod(CarbonInterface $from, CarbonInterface $to, int $lineLimit = 50, ?string $currency = null): array
    {
        $userId = Auth::id();

        $avgCost = $this->landedCost->weightedAverageCost($userId, $currency);

        $items = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.user_id', $userId)
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$from, $to])
            ->when($currency !== null, fn ($q) => $q->where('orders.currency', $currency))
            ->select(
                'order_items.order_id',
                'order_items.product_id',
                'order_items.quantity',
                'order_items.unit_price',
                'orders.currency',
                'orders.created_at',
                'products.name as product_name'
            )
            ->orderByDesc('orders.created_at')
            ->get();

        $totals = [];
        foreach ($currency !== null ? [$currency] : ['AFN', 'USD'] as $bucket) {
            $totals[$bucket] = ['revenue' => '0.00', 'cost' => '0.00', 'profit' => '0.00', 'qty' => 0, 'missing_cost_lines' => 0, 'returned_revenue' => '0.00', 'returned_qty' => 0];
        }

        // Returns whose return_date falls in the window (date-only column: inclusive
        // lower bound, exclusive upper bound on the day after the window's end).
        $returns = $this->returnsInWindow($userId, $from, $to, $currency);

        // Aggregate items per (order, product, currency) first: an order may
        // carry duplicate product lines, and a return must net against the
        // total sold on that order+product — not per line, which would apply
        // the return credit once per duplicate and over-credit COGS. Revenue
        // is the exact per-line sum; the unit price is only the display average.
        $items = $items->groupBy(fn ($i) => "{$i->order_id}:{$i->product_id}:{$i->currency}")->map(function ($group) {
            $first = $group->first();
            $qty = $group->sum(fn ($i) => (int) $i->quantity);
            // Money is BC, scale 2: Collection::sum() would accumulate the
            // per-line BC strings as floats, so fold with bcadd instead.
            $revenue = $group->reduce(
                fn ($carry, $i) => bcadd(
                    $carry,
                    bcmul(number_format((float) $i->unit_price, 2, '.', ''), (string) (int) $i->quantity, 2),
                    2
                ),
                '0.00'
            );

            return (object) [
                'order_id' => $first->order_id,
                'product_id' => $first->product_id,
                'product_name' => $first->product_name,
                'quantity' => $qty,
                'revenue' => $revenue,
                'unit_price' => $qty > 0 ? bcdiv($revenue, (string) $qty, 2) : '0.00',
                'currency' => $first->currency,
                'created_at' => $first->created_at,
            ];
        })->values();

        $byProduct = [];
        $saleLines = collect();
        $matchedReturns = [];

        foreach ($items as $item) {
            $currency = $item->currency;
            $qty = (int) $item->quantity;
            $sellUnit = number_format((float) $item->unit_price, 2, '.', '');
            $costUnit = $avgCost[$item->product_id][$currency] ?? null;
            $revenue = $item->revenue;
            $lineKey = "{$item->order_id}:{$item->product_id}:{$currency}";

            // Net the sale line against returns booked in this window for the
            // same order + product. Mark the return as matched either way so
            // the reversal pass below never re-applies it.
            $lineReturn = $returns['lines'][$lineKey] ?? null;
            $netQty = $qty;
            $netRevenue = $revenue;
            if ($lineReturn !== null && $lineReturn['qty'] > 0) {
                $matchedReturns[$lineKey] = true;
                $netQty = max(0, $qty - $lineReturn['qty']);
                $netRevenue = max('0.00', bcsub($revenue, (string) $lineReturn['revenue'], 2));
            }

            $totals[$currency]['revenue'] = bcadd($totals[$currency]['revenue'], $netRevenue, 2);
            $totals[$currency]['qty'] += $netQty;

            $key = $item->product_id.':'.$currency;
            if (! isset($byProduct[$key])) {
                $byProduct[$key] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'currency' => $currency,
                    'qty' => 0,
                    'revenue' => '0.00',
                    'cost' => '0.00',
                    'profit' => '0.00',
                    'has_cost' => false,
                ];
            }
            $byProduct[$key]['qty'] += $netQty;
            $byProduct[$key]['revenue'] = bcadd($byProduct[$key]['revenue'], $netRevenue, 2);

            if ($costUnit === null) {
                $totals[$currency]['missing_cost_lines']++;

                $saleLines->push([
                    'order_id' => $item->order_id,
                    'product_name' => $item->product_name,
                    'date' => Carbon::parse($item->created_at),
                    'qty' => $netQty,
                    'cost_unit' => null,
                    'sell_unit' => $sellUnit,
                    'profit' => null,
                    'currency' => $currency,
                    'has_cost' => false,
                ]);

                continue;
            }

            $lineCost = bcmul($costUnit, (string) $netQty, 2);
            $lineProfit = bcsub($netRevenue, $lineCost, 2);

            $totals[$currency]['cost'] = bcadd($totals[$currency]['cost'], $lineCost, 2);
            $totals[$currency]['profit'] = bcadd($totals[$currency]['profit'], $lineProfit, 2);

            $byProduct[$key]['cost'] = bcadd($byProduct[$key]['cost'], $lineCost, 2);
            $byProduct[$key]['profit'] = bcadd($byProduct[$key]['profit'], $lineProfit, 2);
            $byProduct[$key]['has_cost'] = true;

            $saleLines->push([
                'order_id' => $item->order_id,
                'product_name' => $item->product_name,
                'date' => Carbon::parse($item->created_at),
                'qty' => $netQty,
                'cost_unit' => $costUnit,
                'sell_unit' => $sellUnit,
                'profit' => $lineProfit,
                'currency' => $currency,
                'has_cost' => true,
            ]);
        }

        // Returns booked in this window whose SALE is not in the window (sale
        // happened earlier, or the order was cancelled): revenue was reported
        // in a past period (or not at all), so this period carries only the
        // reversal — revenue minus the cost credit nets to the lost margin.
        // Returns whose sale WAS in the window were matched and netted per-sale
        // above; skipping them here avoids double-counting. Returns with no
        // purchase cost basis net their revenue only (credit zero — symmetric
        // with the sale-side missing-cost rule).
        foreach ($returns['lines'] as $lineKey => $lineReturn) {
            if (isset($matchedReturns[$lineKey])) {
                continue;
            }

            $lineCurrency = $lineReturn['currency'];
            $costUnit = $avgCost[$lineReturn['product_id']][$lineCurrency] ?? null;
            $creditCost = $costUnit !== null ? bcmul($costUnit, (string) $lineReturn['qty'], 2) : '0.00';

            $totals[$lineCurrency]['revenue'] = bcsub($totals[$lineCurrency]['revenue'], (string) $lineReturn['revenue'], 2);
            $totals[$lineCurrency]['cost'] = bcsub($totals[$lineCurrency]['cost'], $creditCost, 2);
            $totals[$lineCurrency]['profit'] = bcadd(
                $totals[$lineCurrency]['profit'],
                bcsub($creditCost, (string) $lineReturn['revenue'], 2),
                2
            );
        }

        // Record the period's returned revenue per currency for display. Qty
        // is NOT blanket-subtracted here: matched (same-window) returns were
        // already netted inside their sale lines, and unmatched ones belong to
        // sales outside this window, which never added to this period's qty.
        foreach ($returns['totals'] as $bucket => $r) {
            if (isset($totals[$bucket])) {
                $totals[$bucket]['returned_revenue'] = $r['revenue'];
                $totals[$bucket]['returned_qty'] = $r['qty'];
            }
        }

        // Derive per-unit averages for the product summary.
        $byProduct = collect($byProduct)->map(function ($row) use ($avgCost) {
            $row['avg_cost'] = $avgCost[$row['product_id']][$row['currency']] ?? null;
            $row['avg_sell'] = $row['qty'] > 0
                ? bcdiv($row['revenue'], (string) $row['qty'], 2)
                : '0.00';

            return $row;
        })
            ->filter(fn ($row) => $row['has_cost'])
            ->sortByDesc(fn ($row) => abs((float) $row['profit']))
            ->values();

        return [
            'totals' => $totals,
            'by_product' => $byProduct,
            'sale_lines' => $saleLines->take($lineLimit)->values(),
        ];
    }

    /**
     * Returns booked in the window, per (order, product, currency):
     * 'lines' => ["{order_id}:{product_id}:{currency}" => ['qty' => int, 'revenue' => string, 'product_id' => int, 'currency' => string]]
     * 'totals' => [currency => ['revenue' => string, 'cost' => string, 'qty' => int]].
     *
     * return_date is a date-only column, so the window is an inclusive lower
     * bound and an exclusive day-after upper bound (the same pattern the
     * P&L controller uses for return_date / expense_date). Cost is resolved
     * against the same weighted-average cost basis by the caller.
     */
    private function returnsInWindow(int $userId, CarbonInterface $from, CarbonInterface $to, ?string $currency): array
    {
        $rows = DB::table('order_return_items')
            ->join('order_returns', 'order_return_items.order_return_id', '=', 'order_returns.id')
            ->join('orders', 'order_returns.order_id', '=', 'orders.id')
            ->where('order_returns.user_id', $userId)
            ->where('order_returns.status', '!=', 'cancelled')
            ->where('order_returns.return_date', '>=', $from->toDateString())
            ->where('order_returns.return_date', '<', $to->copy()->addDay()->toDateString())
            ->when($currency !== null, fn ($q) => $q->where('orders.currency', $currency))
            ->select(
                'order_returns.order_id',
                'order_return_items.product_id',
                'orders.currency',
                'order_return_items.quantity',
                'order_return_items.unit_price'
            )
            ->get();

        $lines = [];
        $totals = [];

        foreach ($rows as $row) {
            $qty = (int) $row->quantity;
            $revenue = bcmul(number_format((float) $row->unit_price, 2, '.', ''), (string) $qty, 2);
            $key = "{$row->order_id}:{$row->product_id}:{$row->currency}";

            if (! isset($lines[$key])) {
                $lines[$key] = ['qty' => 0, 'revenue' => '0.00', 'product_id' => $row->product_id, 'currency' => $row->currency];
            }
            $lines[$key]['qty'] += $qty;
            $lines[$key]['revenue'] = bcadd($lines[$key]['revenue'], $revenue, 2);

            if (! isset($totals[$row->currency])) {
                $totals[$row->currency] = ['revenue' => '0.00', 'cost' => '0.00', 'qty' => 0];
            }
            $totals[$row->currency]['revenue'] = bcadd($totals[$row->currency]['revenue'], $revenue, 2);
            $totals[$row->currency]['qty'] += $qty;
        }

        return ['lines' => $lines, 'totals' => $totals];
    }
}
