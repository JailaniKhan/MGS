<?php

namespace App\Services\Reporting;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Per-unit profit/loss for sales in a period.
 *
 * Cost basis: weighted average purchase price per product PER CURRENCY
 * (SUM(unit_price × qty) / SUM(qty) over all non-cancelled purchases,
 * same formula as ReportController::stockReport). A sale is only matched
 * against cost in its own currency — a product bought in AFN and sold in
 * USD has no comparable cost and is flagged, never converted.
 */
class UnitProfitService
{
    /**
     * @param  string|null  $currency  Restrict everything to one currency
     *                                     (e.g. 'USD'); null keeps the default
     *                                     dual-currency AFN/USD buckets.
     *
     * @return array{
     *     totals: array<string, array{revenue: string, cost: string, profit: string, qty: int, missing_cost_lines: int}>,
     *     by_product: Collection,
     *     sale_lines: Collection,
     * }
     */
    public function forPeriod(CarbonInterface $from, CarbonInterface $to, int $lineLimit = 50, ?string $currency = null): array
    {
        $userId = Auth::id();

        $avgCost = $this->weightedAverageCost($userId, $currency);

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
            $totals[$bucket] = ['revenue' => '0.00', 'cost' => '0.00', 'profit' => '0.00', 'qty' => 0, 'missing_cost_lines' => 0];
        }

        $byProduct = [];
        $saleLines = collect();

        foreach ($items as $item) {
            $currency = $item->currency;
            $qty = (int) $item->quantity;
            $sellUnit = number_format((float) $item->unit_price, 2, '.', '');
            $costUnit = $avgCost[$item->product_id][$currency] ?? null;
            $revenue = bcmul($sellUnit, (string) $qty, 2);

            $totals[$currency]['revenue'] = bcadd($totals[$currency]['revenue'], $revenue, 2);
            $totals[$currency]['qty'] += $qty;

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
            $byProduct[$key]['qty'] += $qty;
            $byProduct[$key]['revenue'] = bcadd($byProduct[$key]['revenue'], $revenue, 2);

            if ($costUnit === null) {
                $totals[$currency]['missing_cost_lines']++;

                $saleLines->push([
                    'order_id' => $item->order_id,
                    'product_name' => $item->product_name,
                    'date' => Carbon::parse($item->created_at),
                    'qty' => $qty,
                    'cost_unit' => null,
                    'sell_unit' => $sellUnit,
                    'profit' => null,
                    'currency' => $currency,
                    'has_cost' => false,
                ]);

                continue;
            }

            $lineCost = bcmul($costUnit, (string) $qty, 2);
            $lineProfit = bcsub($revenue, $lineCost, 2);

            $totals[$currency]['cost'] = bcadd($totals[$currency]['cost'], $lineCost, 2);
            $totals[$currency]['profit'] = bcadd($totals[$currency]['profit'], $lineProfit, 2);

            $byProduct[$key]['cost'] = bcadd($byProduct[$key]['cost'], $lineCost, 2);
            $byProduct[$key]['profit'] = bcadd($byProduct[$key]['profit'], $lineProfit, 2);
            $byProduct[$key]['has_cost'] = true;

            $saleLines->push([
                'order_id' => $item->order_id,
                'product_name' => $item->product_name,
                'date' => Carbon::parse($item->created_at),
                'qty' => $qty,
                'cost_unit' => $costUnit,
                'sell_unit' => $sellUnit,
                'profit' => $lineProfit,
                'currency' => $currency,
                'has_cost' => true,
            ]);
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
     * Weighted average purchase cost per product per currency:
     * [product_id => ['AFN' => '20.50', 'USD' => '0.26'], ...].
     *
     * @return array<int, array<string, string>>
     */
    private function weightedAverageCost(int $userId, ?string $currency = null): array
    {
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
            $cost[$row->product_id][$row->currency] = number_format((float) $row->avg_cost, 2, '.', '');
        }

        return $cost;
    }
}
