<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SpendBreakdownController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'month');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Default date range based on period
        $now = Carbon::now();
        if (!$dateFrom) {
            $dateFrom = match($period) {
                'week' => $now->copy()->startOfWeek(),
                'month' => $now->copy()->startOfMonth(),
                'year' => $now->copy()->startOfYear(),
                default => $now->copy()->startOfMonth(),
            };
        }
        if (!$dateTo) {
            $dateTo = $now->copy()->endOfDay();
        }

        $expenses = Expense::whereBetween('expense_date', [$dateFrom, $dateTo])->get();

        // Group by category
        $byCategory = $expenses->groupBy('category')->map(function ($items, $category) {
            $afn = $items->where('currency', 'AFN')->sum('amount');
            $usd = $items->where('currency', 'USD')->sum('amount');
            return [
                'category' => $category,
                'count' => $items->count(),
                'afn' => $afn,
                'usd' => $usd,
                'total' => $afn + $usd * 80,
            ];
        })->sortByDesc('total')->values();

        $totalExpenses = $byCategory->sum('total');
        $totalByCurrencyAFN = $expenses->where('currency', 'AFN')->sum('amount');
        $totalByCurrencyUSD = $expenses->where('currency', 'USD')->sum('amount');

        // Daily trend
        $dailyTrend = $expenses->groupBy(function ($item) {
            return $item->expense_date->format('Y-m-d');
        })->map(function ($items, $date) {
            $afn = $items->where('currency', 'AFN')->sum('amount');
            $usd = $items->where('currency', 'USD')->sum('amount');
            return [
                'date' => $date,
                'total' => $afn + $usd * 80,
            ];
        })->sortBy('date')->values();

        return view('spend-breakdown.index', compact(
            'byCategory', 'totalExpenses', 'totalByCurrencyAFN', 'totalByCurrencyUSD',
            'dailyTrend', 'period', 'dateFrom', 'dateTo'
        ));
    }
}
