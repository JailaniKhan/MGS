<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\LedgerEntry;

class DashboardController extends Controller
{
    public function index()
    {
        // Total revenue from order payments + ledger customer payments
        $totalRevenueAFN = Payment::where('currency', 'AFN')->sum('amount')
            + LedgerEntry::where('currency', 'AFN')->where('type', 'payment_received')->sum('amount');
        $totalRevenueUSD = Payment::where('currency', 'USD')->sum('amount')
            + LedgerEntry::where('currency', 'USD')->where('type', 'payment_received')->sum('amount');

        // Total expenses (purchase payments + ledger supplier payments)
        $totalExpenseAFN = PurchasePayment::where('currency', 'AFN')->sum('amount')
            + LedgerEntry::where('currency', 'AFN')->where('type', 'payment_made')->sum('amount');
        $totalExpenseUSD = PurchasePayment::where('currency', 'USD')->sum('amount')
            + LedgerEntry::where('currency', 'USD')->where('type', 'payment_made')->sum('amount');

        $data = [
            'totalCustomers' => Customer::count(),
            'totalProducts' => Product::count(),
            'totalOrders' => Order::count(),
            'totalPurchases' => Purchase::count(),
            'totalRevenueAFN' => $totalRevenueAFN,
            'totalRevenueUSD' => $totalRevenueUSD,
            'totalExpenseAFN' => $totalExpenseAFN,
            'totalExpenseUSD' => $totalExpenseUSD,
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'processingOrders' => Order::where('status', 'processing')->count(),
            'recentOrders' => Order::with('customer')->orderBy('created_at', 'desc')->take(5)->get(),
            'recentPurchases' => Purchase::with('supplier')->orderBy('created_at', 'desc')->take(5)->get(),
            'lowStockProducts' => Product::where('stock', '<', 10)->count(),
            'pendingPayments' => Order::where('status', '!=', 'cancelled')->get()->filter(function ($order) {
                return $order->remaining_amount > 0;
            })->count(),
        ];

        return view('dashboard', $data);
    }
}
