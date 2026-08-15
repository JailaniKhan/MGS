<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SpendBreakdownController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'month');
        $dateFrom = $request->get('date_from');

        // The date input is the anchor: the period (week/month/year) is derived from it.
        $anchor = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now();

        [$dateFrom, $dateTo] = match ($period) {
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->startOfWeek()->addDays(6)],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
            default => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
        };
        $dateTo = Carbon::parse($dateTo)->endOfDay();

        // `expense_date` may hold either a date-only ('Y-m-d') value or a full
        // timestamp, so a string `<=` bound on endOfDay would silently drop the
        // FIRST day of the period when it is stored date-only. An inclusive lower
        // bound on the date string plus an exclusive upper bound handles both forms.
        $toExclusive = $dateTo->copy()->addDay()->toDateString();

        $expenses = Expense::where('expense_date', '>=', $dateFrom->toDateString())
            ->where('expense_date', '<', $toExclusive)
            ->get();

        // Group by category - keep AFN and USD separate (no blending)
        $byCategory = $expenses->groupBy('category')->map(function ($items, $category) {
            return [
                'category' => $category,
                'count' => $items->count(),
                'afn' => $items->where('currency', 'AFN')->reduce(fn ($carry, $expense) => bcadd($carry, (string) $expense->amount, 2), '0.00'),
                'usd' => $items->where('currency', 'USD')->reduce(fn ($carry, $expense) => bcadd($carry, (string) $expense->amount, 2), '0.00'),
            ];
        })->sortByDesc(function ($category) {
            return max((float) $category['afn'], (float) $category['usd']);
        })->values();

        $totalAFN = $expenses->where('currency', 'AFN')->reduce(fn ($carry, $expense) => bcadd($carry, (string) $expense->amount, 2), '0.00');
        $totalUSD = $expenses->where('currency', 'USD')->reduce(fn ($carry, $expense) => bcadd($carry, (string) $expense->amount, 2), '0.00');

        // Daily trend - per currency, no conversion
        $dailyTrend = $expenses->groupBy(function ($item) {
            return $item->expense_date->format('Y-m-d');
        })->map(function ($items, $date) {
            return [
                'date' => $date,
                'afn' => $items->where('currency', 'AFN')->reduce(fn ($carry, $expense) => bcadd($carry, (string) $expense->amount, 2), '0.00'),
                'usd' => $items->where('currency', 'USD')->reduce(fn ($carry, $expense) => bcadd($carry, (string) $expense->amount, 2), '0.00'),
            ];
        })->sortBy('date')->values();

        return view('spend-breakdown.index', compact(
            'byCategory', 'totalAFN', 'totalUSD',
            'dailyTrend', 'period', 'dateFrom', 'dateTo'
        ))->with([
            'dateFromInput' => $anchor->toDateString(),
        ]);
    }
}
