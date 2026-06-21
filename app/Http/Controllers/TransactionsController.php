<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Purchase;
use Illuminate\Http\Request;

class TransactionsController extends Controller
{
    public function index()
    {
        $orders = Order::with('customer', 'orderItems.product')->orderBy('created_at', 'desc')->get();
        $purchases = Purchase::with('supplier')->orderBy('created_at', 'desc')->get();
        return view('transactions.index', compact('orders', 'purchases'));
    }
}