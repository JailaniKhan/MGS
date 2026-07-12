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

        $expenses = Expense::whereBetween('expense_date', [$dateFrom, $dateTo])
            ->get()
            ->filter(fn ($expense) => $expense->expense_date !== null);

        // Group by category - keep AFN and USD separate (no blending)
        $byCategory = $expenses->groupBy('category')->map(function ($items, $category) {
            return [
                'category' => $category,
                'count' => $items->count(),
                'afn' => $items->where('currency', 'AFN')->sum('amount'),
                'usd' => $items->where('currency', 'USD')->sum('amount'),
            ];
        })->sortByDesc(function ($category) {
            return max((float) $category['afn'], (float) $category['usd']);
        })->values();

        $totalAFN = $expenses->where('currency', 'AFN')->sum('amount');
        $totalUSD = $expenses->where('currency', 'USD')->sum('amount');

        // Daily trend - per currency, no conversion
        $dailyTrend = $expenses->groupBy(function ($item) {
            return $item->expense_date->format('Y-m-d');
        })->map(function ($items, $date) {
            return [
                'date' => $date,
                'afn' => $items->where('currency', 'AFN')->sum('amount'),
                'usd' => $items->where('currency', 'USD')->sum('amount'),
            ];
        })->sortBy('date')->values();

        return view('spend-breakdown.index', compact(
            'byCategory', 'totalAFN', 'totalUSD',
            'dailyTrend', 'period', 'dateFrom', 'dateTo'
        ));
    }
}
