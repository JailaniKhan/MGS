<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Category;

class DashboardController extends Controller
{
    public function index()
    {
        $data = [
            'totalCustomers' => Customer::count(),
            'totalProducts' => Product::count(),
            'totalOrders' => Order::count(),
            'totalRevenue' => Order::where('status', 'completed')->sum('total_amount'),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'processingOrders' => Order::where('status', 'processing')->count(),
            'recentOrders' => Order::with('customer')->orderBy('created_at', 'desc')->take(5)->get(),
            'lowStockProducts' => Product::where('stock', '<', 10)->count(),
        ];

        return view('dashboard', $data);
    }
}