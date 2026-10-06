<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Purchase;

class TransactionsController extends Controller
{
    public function index()
    {
        $orders = Order::with(['customer', 'supplier'])
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE, ['*'], 'orders_page')
            ->withQueryString();

        $purchases = Purchase::with(['customer', 'supplier'])
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE, ['*'], 'purchases_page')
            ->withQueryString();

        return view('transactions.index', compact('orders', 'purchases'));
    }
}
