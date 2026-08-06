<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
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
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
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
                'in' => (float) Payment::where('currency', $currency)->sum('amount')
                    + (float) PartyPayment::where('currency', $currency)->where('type', 'payment_received')->sum('amount')
                    + (float) $this->cashbookSum('cashbook_in', 'debit', $currency),
                'out' => (float) PurchasePayment::where('currency', $currency)->sum('amount')
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
     * Single day's cash in/out per currency (payments use their created_at,
     * cashbook entries their transaction_date, expenses their expense_date).
     */
    protected function dailyCashFlow(string $date): array
    {
        $flow = [];

        foreach (['AFN', 'USD'] as $currency) {
            $flow[$currency] = [
                'in' => (float) Payment::where('currency', $currency)->whereDate('created_at', $date)->sum('amount')
                    + (float) PartyPayment::where('currency', $currency)->where('type', 'payment_received')->whereDate('created_at', $date)->sum('amount')
                    + (float) DB::table('journal_entries')
                        ->join('ledger_entries', 'ledger_entries.journal_entry_id', '=', 'journal_entries.id')
                        ->where('journal_entries.user_id', auth()->id())
                        ->where('journal_entries.source', 'cashbook_in')
                        ->where('journal_entries.currency', $currency)
                        ->where('ledger_entries.direction', 'debit')
                        ->whereDate('journal_entries.transaction_date', $date)
                        ->sum('ledger_entries.amount'),
                'out' => (float) PurchasePayment::where('currency', $currency)->whereDate('created_at', $date)->sum('amount')
                    + (float) PartyPayment::where('currency', $currency)->where('type', 'payment_made')->whereDate('created_at', $date)->sum('amount')
                    + (float) DB::table('journal_entries')
                        ->join('ledger_entries', 'ledger_entries.journal_entry_id', '=', 'journal_entries.id')
                        ->where('journal_entries.user_id', auth()->id())
                        ->where('journal_entries.source', 'cashbook_out')
                        ->where('journal_entries.currency', $currency)
                        ->where('ledger_entries.direction', 'credit')
                        ->whereDate('journal_entries.transaction_date', $date)
                        ->sum('ledger_entries.amount')
                    + (float) Expense::where('currency', $currency)->whereDate('expense_date', $date)->sum('amount')
                    + (float) SalaryPayment::where('currency', $currency)
                        ->whereHas('employee', fn ($q) => $q->where('user_id', auth()->id()))
                        ->whereDate('created_at', $date)
                        ->sum('amount'),
            ];
        }

        return $flow;
    }

    protected function weeklyRevenue()
    {
        $labels = [];
        $data = [];
        $dataAFN = [];
        $dataUSD = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('m/d');
            $flow = $this->dailyCashFlow($date);
            $dataAFN[] = $flow['AFN']['in'];
            $dataUSD[] = $flow['USD']['in'];
            $data[] = $flow['AFN']['in'] + $flow['USD']['in'] * $this->usdToAfn();
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'data_afn' => $dataAFN,
            'data_usd' => $dataUSD,
            'datasets' => [
                [
                    'label' => 'Weekly Revenue',
                    'data' => $data,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                ],
            ],
        ];
    }

    protected function weeklyExpenses()
    {
        $labels = [];
        $data = [];
        $dataAFN = [];
        $dataUSD = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('m/d');
            $flow = $this->dailyCashFlow($date);
            $dataAFN[] = $flow['AFN']['out'];
            $dataUSD[] = $flow['USD']['out'];
            $data[] = $flow['AFN']['out'] + $flow['USD']['out'] * $this->usdToAfn();
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'data_afn' => $dataAFN,
            'data_usd' => $dataUSD,
            'datasets' => [
                [
                    'label' => 'Weekly Expenses',
                    'data' => $data,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                ],
            ],
        ];
    }

    protected function topProducts()
    {
        $items = OrderItem::selectRaw('product_id, SUM(quantity) as total_quantity')
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->groupBy('product_id')
            ->orderBy('total_quantity', 'desc')
            ->take(5)
            ->get();

        $productNames = Product::whereIn('id', $items->pluck('product_id'))->pluck('name', 'id');

        $labels = [];
        $data = [];
        foreach ($items as $item) {
            $labels[] = $productNames[$item->product_id] ?? 'Unknown';
            $data[] = $item->total_quantity;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Quantity Sold',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                    ],
                    'borderColor' => [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                    ],
                ],
            ],
        ];
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
            $key = $row->person_type . ':' . $row->person_id;
            $pending[$key]['type'] = $row->person_type;
            $pending[$key]['id'] = $row->person_id;
            $pending[$key]['owed'][$row->currency] = (float) $row->remaining;
            $pending[$key]['due'][$row->currency] = 0;
        }
        foreach ($purchases as $row) {
            $key = $row->person_type . ':' . $row->person_id;
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
        $weeklyRevenue = $this->weeklyRevenue();
        $weeklyExpenses = $this->weeklyExpenses();
        $topProducts = $this->topProducts();

        $totals = $this->cashFlowTotals();

        $data = [
            'totalRevenueAFN' => $totals['AFN']['in'],
            'totalRevenueUSD' => $totals['USD']['in'],
            'totalExpenseAFN' => $totals['AFN']['out'],
            'totalExpenseUSD' => $totals['USD']['out'],
            'pendingOrders' => Order::where('status', '!=', 'cancelled')->get()->filter(function ($order) {
                return $order->remaining_amount > 0;
            })->count(),
            'processingOrders' => Order::where('status', 'processing')->count(),
            'recentOrders' => Order::with('customer')->orderBy('created_at', 'desc')->take(5)->get(),
            'recentPurchases' => Purchase::with('supplier')->orderBy('created_at', 'desc')->take(5)->get(),
            'lowStockProducts' => Product::where('stock', '<', 10)->count(),
            'pendingPayments' => Purchase::where('status', '!=', 'cancelled')->get()->filter(function ($purchase) {
                return $purchase->remaining_amount > 0;
            })->count(),
            'rate' => $this->usdToAfn(),
            'weeklyRevenue' => $weeklyRevenue,
            'weeklyExpenses' => $weeklyExpenses,
            'topProducts' => $topProducts,
            'topDebtors' => $this->topDebtors(),
            'recentReminders' => Reminder::with('remindable')
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->get(),
        ];

        return view('dashboard', $data);
    }
}
