<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;

class DashboardController extends Controller
{
    public function index()
    {
        $data = [
            'totalCustomers' => Customer::count(),
            'totalProducts' => Product::count(),
            'totalOrders' => Order::count(),
            'totalRevenueAFN' => Payment::where('currency', 'AFN')->sum('amount'),
            'totalRevenueUSD' => Payment::where('currency', 'USD')->sum('amount'),
            'totalRevenue' => Payment::sum('amount'),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'processingOrders' => Order::where('status', 'processing')->count(),
            'recentOrders' => Order::with('customer')->orderBy('created_at', 'desc')->take(5)->get(),
            'lowStockProducts' => Product::where('stock', '<', 10)->count(),
            'pendingPayments' => Order::where('status', '!=', 'cancelled')->get()->filter(function ($order) {
                return $order->remaining_amount > 0;
            })->count(),
        ];

        return view('dashboard', $data);
    }
}