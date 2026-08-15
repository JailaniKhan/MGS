<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Reminder;
use App\Models\SalaryPayment;
use App\Models\Setting;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Cache TTL for dashboard aggregates (seconds). */
    protected const DASHBOARD_TTL = 120;

    /**
     * USD -> AFN conversion rate, sourced from settings (Fix M5).
     */
    protected function usdToAfn(): float
    {
        return (float) Setting::get('usd_to_afn_rate', 80);
    }

    /**
     * Cash in/out per currency across every flow the app records:
     * order payments, purchase payments, ledger (party) payments,
     * cashbook journal entries, standalone expenses and salaries.
     *
     * Cashbook entries carry two equal ledger lines (cash + income/expense);
     * the cash side is the debit for cashbook_in and the credit for
     * cashbook_out, so sum one side only to avoid doubling.
     */
    protected function cashFlowTotals(): array
    {
        $totals = [];

        foreach (['AFN', 'USD'] as $currency) {
            $totals[$currency] = [
                'in' => (float) Payment::where('currency', $currency)
                    ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
                    ->sum('amount')
                    + (float) PartyPayment::where('currency', $currency)->where('type', 'payment_received')->sum('amount')
                    + (float) $this->cashbookSum('cashbook_in', 'debit', $currency),
                'out' => (float) PurchasePayment::where('currency', $currency)
                    ->whereHas('purchase', fn ($q) => $q->where('status', '!=', 'cancelled'))
                    ->sum('amount')
                    + (float) PartyPayment::where('currency', $currency)->where('type', 'payment_made')->sum('amount')
                    + (float) $this->cashbookSum('cashbook_out', 'credit', $currency)
                    + (float) Expense::where('currency', $currency)->sum('amount')
                    + (float) $this->salarySum($currency),
            ];
        }

        return $totals;
    }

    protected function cashbookSum(string $source, string $direction, string $currency): float
    {
        return (float) DB::table('journal_entries')
            ->join('ledger_entries', 'ledger_entries.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.user_id', auth()->id())
            ->where('journal_entries.source', $source)
            ->where('journal_entries.currency', $currency)
            ->where('ledger_entries.direction', $direction)
            ->sum('ledger_entries.amount');
    }

    protected function salarySum(string $currency): float
    {
        return (float) SalaryPayment::where('currency', $currency)
            ->whereHas('employee', fn ($q) => $q->where('user_id', auth()->id()))
            ->sum('amount');
    }

    /**
     * Weekly revenue/expense series, built from ONE grouped query per side
     * instead of 7 per-day query batches (was ~168 queries; now ~6).
     */
    protected function weeklySeries(string $side): array
    {
        $userId = auth()->id();
        $start = Carbon::now()->subDays(6)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::now()->subDays($i);
            $days[$d->format('Y-m-d')] = [
                'label' => $d->format('m/d'),
                'AFN' => 0.0, 'USD' => 0.0,
            ];
        }

        $bucket = function ($rows, $dateCol) use (&$days) {
            foreach ($rows as $r) {
                $key = Carbon::parse($r->{$dateCol})->format('Y-m-d');
                if (isset($days[$key])) {
                    $days[$key][$r->currency] += (float) $r->total;
                }
            }
        };

        if ($side === 'in') {
            $bucket(Payment::selectRaw('DATE(created_at) as d, currency, SUM(amount) as total')
                ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->where('created_at', '>=', $start)->where('created_at', '<=', $end)
                ->groupBy('d', 'currency')->get(), 'd');
            $bucket(PartyPayment::selectRaw('DATE(created_at) as d, currency, SUM(amount) as total')
                ->where('type', 'payment_received')
                ->where('created_at', '>=', $start)->where('created_at', '<=', $end)
                ->groupBy('d', 'currency')->get(), 'd');
            $bucket(DB::table('journal_entries')
                ->join('ledger_entries', 'ledger_entries.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.user_id', $userId)
                ->where('journal_entries.source', 'cashbook_in')
                ->where('ledger_entries.direction', 'debit')
                ->where('journal_entries.transaction_date', '>=', $start)
                ->where('journal_entries.transaction_date', '<=', $end)
                ->groupBy(DB::raw('DATE(journal_entries.transaction_date)'), 'journal_entries.currency')
                ->selectRaw('DATE(journal_entries.transaction_date) as d, journal_entries.currency, SUM(ledger_entries.amount) as total')
                ->get(), 'd');
        } else {
            $bucket(PurchasePayment::selectRaw('DATE(created_at) as d, currency, SUM(amount) as total')
                ->whereHas('purchase', fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->where('created_at', '>=', $start)->where('created_at', '<=', $end)
                ->groupBy('d', 'currency')->get(), 'd');
            $bucket(PartyPayment::selectRaw('DATE(created_at) as d, currency, SUM(amount) as total')
                ->where('type', 'payment_made')
                ->where('created_at', '>=', $start)->where('created_at', '<=', $end)
                ->groupBy('d', 'currency')->get(), 'd');
            $bucket(DB::table('journal_entries')
                ->join('ledger_entries', 'ledger_entries.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.user_id', $userId)
                ->where('journal_entries.source', 'cashbook_out')
                ->where('ledger_entries.direction', 'credit')
                ->where('journal_entries.transaction_date', '>=', $start)
                ->where('journal_entries.transaction_date', '<=', $end)
                ->groupBy(DB::raw('DATE(journal_entries.transaction_date)'), 'journal_entries.currency')
                ->selectRaw('DATE(journal_entries.transaction_date) as d, journal_entries.currency, SUM(ledger_entries.amount) as total')
                ->get(), 'd');
            $bucket(Expense::selectRaw('DATE(expense_date) as d, currency, SUM(amount) as total')
                ->where('expense_date', '>=', $start)->where('expense_date', '<=', $end)
                ->groupBy('d', 'currency')->get(), 'd');
            $bucket(SalaryPayment::selectRaw('DATE(salary_payments.created_at) as d, salary_payments.currency, SUM(salary_payments.amount) as total')
                ->join('employees', 'salary_payments.employee_id', '=', 'employees.id')
                ->where('employees.user_id', $userId)
                ->where('salary_payments.created_at', '>=', $start)->where('salary_payments.created_at', '<=', $end)
                ->groupBy('d', 'salary_payments.currency')->get(), 'd');
        }

        $labels = [];
        $dataAFN = [];
        $dataUSD = [];
        $data = [];
        $rate = $this->usdToAfn();
        foreach ($days as $day) {
            $labels[] = $day['label'];
            $dataAFN[] = $day['AFN'];
            $dataUSD[] = $day['USD'];
            $data[] = $day['AFN'] + $day['USD'] * $rate;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'data_afn' => $dataAFN,
            'data_usd' => $dataUSD,
        ];
    }

    protected function weeklyRevenue()
    {
        $series = $this->weeklySeries('in');
        $series['datasets'] = [[
            'label' => 'Weekly Revenue',
            'data' => $series['data'],
            'backgroundColor' => 'rgba(16, 174, 100, 0.2)',
            'borderColor' => 'rgba(16, 174, 100, 1)',
        ]];

        return $series;
    }

    protected function weeklyExpenses()
    {
        $series = $this->weeklySeries('out');
        $series['datasets'] = [[
            'label' => 'Weekly Expenses',
            'data' => $series['data'],
            'backgroundColor' => 'rgba(230, 85, 85, 0.2)',
            'borderColor' => 'rgba(230, 85, 85, 1)',
        ]];

        return $series;
    }

    /**
     * Combined debtors/creditors across customers and suppliers, linked by
     * the canonical person_type / person_id. Positive net = they owe you
     * (debtor), negative = you owe them (creditor). Cancelled documents are
     * excluded and returns are subtracted, matching Order::remaining_amount.
     */
    protected function topDebtors()
    {
        $orders = DB::table('orders')
            ->leftJoinSub(
                DB::table('payments')->selectRaw('order_id, SUM(amount) as paid')->groupBy('order_id'),
                'p', 'p.order_id', '=', 'orders.id'
            )
            ->leftJoinSub(
                DB::table('order_returns')->where('status', '!=', 'cancelled')
                    ->selectRaw('order_id, SUM(total_amount) as returned')->groupBy('order_id'),
                'r', 'r.order_id', '=', 'orders.id'
            )
            ->where('orders.status', '!=', 'cancelled')
            ->whereNotNull('orders.person_type')
            ->where('orders.user_id', auth()->id())
            ->groupBy('orders.person_type', 'orders.person_id', 'orders.currency')
            ->selectRaw('orders.person_type, orders.person_id, orders.currency, MAX(0, SUM(orders.total_amount) - COALESCE(SUM(p.paid), 0) - COALESCE(SUM(r.returned), 0)) as remaining')
            ->get();

        $purchases = DB::table('purchases')
            ->leftJoinSub(
                DB::table('purchase_payments')->selectRaw('purchase_id, SUM(amount) as paid')->groupBy('purchase_id'),
                'p', 'p.purchase_id', '=', 'purchases.id'
            )
            ->leftJoinSub(
                DB::table('purchase_returns')->where('status', '!=', 'cancelled')
                    ->selectRaw('purchase_id, SUM(total_amount) as returned')->groupBy('purchase_id'),
                'r', 'r.purchase_id', '=', 'purchases.id'
            )
            ->where('purchases.status', '!=', 'cancelled')
            ->whereNotNull('purchases.person_type')
            ->where('purchases.user_id', auth()->id())
            ->groupBy('purchases.person_type', 'purchases.person_id', 'purchases.currency')
            ->selectRaw('purchases.person_type, purchases.person_id, purchases.currency, MAX(0, SUM(purchases.total_amount) - COALESCE(SUM(p.paid), 0) - COALESCE(SUM(r.returned), 0)) as remaining')
            ->get();

        // Combine: orders add to what they owe us, purchases add to what we owe them.
        $pending = [];
        foreach ($orders as $row) {
            $key = $row->person_type.':'.$row->person_id;
            $pending[$key]['type'] = $row->person_type;
            $pending[$key]['id'] = $row->person_id;
            $pending[$key]['owed'][$row->currency] = (float) $row->remaining;
            $pending[$key]['due'][$row->currency] = 0;
        }
        foreach ($purchases as $row) {
            $key = $row->person_type.':'.$row->person_id;
            if (! isset($pending[$key])) {
                $pending[$key]['type'] = $row->person_type;
                $pending[$key]['id'] = $row->person_id;
                $pending[$key]['owed'] = ['AFN' => 0, 'USD' => 0];
            }
            $pending[$key]['due'][$row->currency] = (float) $row->remaining;
        }

        $customerNames = Customer::whereIn('id', collect($pending)->where('type', 'customer')->pluck('id'))->pluck('name', 'id');
        $supplierNames = Supplier::whereIn('id', collect($pending)->where('type', 'supplier')->pluck('id'))->pluck('name', 'id');

        $debtors = collect($pending)
            ->map(function ($row) use ($customerNames, $supplierNames) {
                $netAFN = ($row['owed']['AFN'] ?? 0) - ($row['due']['AFN'] ?? 0);
                $netUSD = ($row['owed']['USD'] ?? 0) - ($row['due']['USD'] ?? 0);
                $names = $row['type'] === 'customer' ? $customerNames : $supplierNames;

                return (object) [
                    'id' => $row['id'],
                    'type' => $row['type'],
                    'name' => $names[$row['id']] ?? __('messages.deleted'),
                    'pending_afn' => $netAFN,
                    'pending_usd' => $netUSD,
                ];
            })
            ->filter(fn ($d) => $d->pending_afn != 0 || $d->pending_usd != 0)
            ->sortByDesc(fn ($d) => abs($d->pending_afn + $d->pending_usd * $this->usdToAfn()))
            ->take(8)
            ->values();

        return $debtors;
    }

    public function index()
    {
        $cacheKey = sprintf('dashboard_v3_%s_%s', auth()->id(), now()->format('Ymd'));

        // Only scalar aggregates are cached; collections (recentOrders,
        // recentPurchases, topDebtors, recentReminders) are always fresh
        // so the view receives live Eloquent models with ->created_at etc.
        $aggregates = Cache::remember($cacheKey, self::DASHBOARD_TTL, function () {
            $totals = $this->cashFlowTotals();
            $lowStockThreshold = (int) Setting::get('min_stock_threshold', 10);

            return [
                'totalRevenueAFN' => (float) $totals['AFN']['in'],
                'totalRevenueUSD' => (float) $totals['USD']['in'],
                'totalExpenseAFN' => (float) $totals['AFN']['out'],
                'totalExpenseUSD' => (float) $totals['USD']['out'],
                // Pending = remaining_amount > 0, mirroring the model accessors:
                // only same-currency payments settle a document, and returns
                // (non-cancelled) reduce the balance before the comparison.
                'pendingOrders' => (int) Order::where('status', '!=', 'cancelled')
                    ->whereRaw('orders.total_amount > (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.order_id = orders.id AND p.currency = orders.currency) + (SELECT COALESCE(SUM(r.total_amount), 0) FROM order_returns r WHERE r.order_id = orders.id AND r.status != ? AND r.currency = orders.currency)', ['cancelled'])
                    ->count(),
                'processingOrders' => (int) Order::where('status', 'processing')->count(),
                'lowStockProducts' => (int) Product::where('stock', '<', $lowStockThreshold)->count(),
                'pendingPayments' => (int) Purchase::where('status', '!=', 'cancelled')
                    ->whereRaw('purchases.total_amount > (SELECT COALESCE(SUM(p.amount), 0) FROM purchase_payments p WHERE p.purchase_id = purchases.id AND p.currency = purchases.currency) + (SELECT COALESCE(SUM(r.total_amount), 0) FROM purchase_returns r WHERE r.purchase_id = purchases.id AND r.status != ? AND r.currency = purchases.currency)', ['cancelled'])
                    ->count(),
                'rate' => (float) $this->usdToAfn(),
                // Chart series — primitives only (labels[], data[], data_afn[], data_usd[])
                'weeklyRevenue' => $this->pluckChartSeries($this->weeklyRevenue()),
                'weeklyExpenses' => $this->pluckChartSeries($this->weeklyExpenses()),
            ];
        });

        // Data fetched fresh per request (must be live Eloquent collections)
        $recentOrders = Order::with('customer')->orderBy('created_at', 'desc')->take(5)->get();
        $recentPurchases = Purchase::with('supplier')->orderBy('created_at', 'desc')->take(5)->get();
        $recentReminders = Reminder::with('remindable')->orderBy('created_at', 'desc')->take(3)->get();
        $topDebtors = $this->topDebtors();

        $data = array_merge($aggregates, [
            'recentOrders' => $recentOrders,
            'recentPurchases' => $recentPurchases,
            'recentReminders' => $recentReminders,
            'topDebtors' => $topDebtors,
        ]);

        return view('dashboard', $data);
    }

    /**
     * Strip chart series to plain arrays (labels/data/data_afn/data_usd) —
     * must be primitives for the cache store to safely round-trip them.
     */
    protected function pluckChartSeries(array $series): array
    {
        return [
            'labels' => array_values($series['labels'] ?? []),
            'data' => array_values($series['data'] ?? []),
            'data_afn' => array_values($series['data_afn'] ?? []),
            'data_usd' => array_values($series['data_usd'] ?? []),
        ];
    }
}
