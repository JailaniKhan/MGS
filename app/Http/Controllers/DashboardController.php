<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\PartyPayment;
use App\Models\OrderItem;
use App\Models\Reminder;
use App\Models\Setting;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * USD -> AFN conversion rate, sourced from settings (Fix M5).
     */
    protected function usdToAfn(): float
    {
        return (float) Setting::get('usd_to_afn_rate', 80);
    }

    protected function weeklyRevenue()
    {
        $labels = [];
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('m/d');
            $dailyRevenueAFN = Payment::where('currency', 'AFN')
                ->whereDate('created_at', $date)
                ->sum('amount');
            $dailyRevenueUSD = Payment::where('currency', 'USD')
                ->whereDate('created_at', $date)
                ->sum('amount') * $this->usdToAfn();
            $data[] = $dailyRevenueAFN + $dailyRevenueUSD;
        }

        return [
            'labels' => $labels,
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
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('m/d');
            $dailyExpenseAFN = PurchasePayment::where('currency', 'AFN')
                ->whereDate('created_at', $date)
                ->sum('amount');
            $dailyExpenseUSD = PurchasePayment::where('currency', 'USD')
                ->whereDate('created_at', $date)
                ->sum('amount') * $this->usdToAfn();
            $data[] = $dailyExpenseAFN + $dailyExpenseUSD;
        }

        return [
            'labels' => $labels,
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
            ->groupBy('product_id')
            ->orderBy('total_quantity', 'desc')
            ->take(5)
            ->get();

        $labels = [];
        $data = [];
        foreach ($items as $item) {
            $product = Product::find($item->product_id);
            $labels[] = $product ? $product->name : 'Unknown';
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

    protected function topDebtors()
    {
        $customers = Customer::withSum('orders', 'total_amount')
            ->get()
            ->filter(function ($customer) {
                $paidAFN = Payment::whereHas('order', fn($q) => $q->where('customer_id', $customer->id))
                    ->where('currency', 'AFN')->sum('amount')
                    + PartyPayment::where('person_type', 'customer')
                        ->where('person_id', $customer->id)
                        ->where('currency', 'AFN')
                        ->where('type', 'payment_received')
                        ->sum('amount');

                $totalAFN = $customer->orders()->where('currency', 'AFN')->sum('total_amount');
                $remainingAFN = max(0, $totalAFN - $paidAFN);

                $paidUSD = Payment::whereHas('order', fn($q) => $q->where('customer_id', $customer->id))
                    ->where('currency', 'USD')->sum('amount')
                    + PartyPayment::where('person_type', 'customer')
                        ->where('person_id', $customer->id)
                        ->where('currency', 'USD')
                        ->where('type', 'payment_received')
                        ->sum('amount');

                $totalUSD = $customer->orders()->where('currency', 'USD')->sum('total_amount');
                $remainingUSD = max(0, $totalUSD - $paidUSD);

                $customer->pending_afn = $remainingAFN;
                $customer->pending_usd = $remainingUSD;

                return $remainingAFN > 0 || $remainingUSD > 0;
            })
            ->sortByDesc(function ($c) {
                return $c->pending_afn + $c->pending_usd * $this->usdToAfn();
            })
            ->take(5)
            ->values();

        return $customers;
    }

    public function index()
    {
        $weeklyRevenue = $this->weeklyRevenue();
        $weeklyExpenses = $this->weeklyExpenses();
        $topProducts = $this->topProducts();

        $data = [
            'totalCustomers' => Customer::count(),
            'totalProducts' => Product::count(),
            'totalOrders' => Order::count(),
            'totalPurchases' => Purchase::count(),
            'totalRevenueAFN' => Payment::where('currency', 'AFN')->sum('amount')
                + PartyPayment::where('currency', 'AFN')->where('type', 'payment_received')->sum('amount'),
            'totalRevenueUSD' => Payment::where('currency', 'USD')->sum('amount')
                + PartyPayment::where('currency', 'USD')->where('type', 'payment_received')->sum('amount'),
            'totalExpenseAFN' => PurchasePayment::where('currency', 'AFN')->sum('amount')
                + PartyPayment::where('currency', 'AFN')->where('type', 'payment_made')->sum('amount'),
            'totalExpenseUSD' => PurchasePayment::where('currency', 'USD')->sum('amount')
                + PartyPayment::where('currency', 'USD')->where('type', 'payment_made')->sum('amount'),
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
            'pendingReminderCount' => Reminder::where('status', 'pending')->count(),
        ];

        return view('dashboard', $data);
    }
}
