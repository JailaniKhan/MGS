<?php

namespace App\Http\Controllers;

use App\Models\OrderReturn;
use App\Models\Order;
use App\Models\OrderReturnItem;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderReturnController extends Controller
{
    public function index()
    {
        $orderReturns = OrderReturn::with('order.customer', 'items.product')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('orders.returns.index', compact('orderReturns'));
    }

    public function create()
    {
        $orders = Order::with('orderItems.product')->orderBy('created_at', 'desc')->get();
        return view('orders.returns.create', compact('orders'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'return_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
            'status' => 'required|in:pending,processing,completed,cancelled',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
        ]);

        $order = Order::with('orderItems.product')->findOrFail($validated['order_id']);

        $subtotal = 0;
        $returnItems = [];

        foreach ($validated['products'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $lineTotal = $item['unit_price'] * $item['quantity'];
            $subtotal += $lineTotal;

            $returnItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $lineTotal,
            ];

            $product->increment('stock', $item['quantity']);
        }

        $orderReturn = OrderReturn::create([
            'order_id' => $validated['order_id'],
            'customer_id' => $order->customer_id,
            'return_date' => $validated['return_date'],
            'reason' => $validated['reason'],
            'total_amount' => $subtotal,
            'status' => $validated['status'],
            'currency' => $order->currency,
        ]);

        $orderReturn->items()->createMany($returnItems);

        return redirect()->route('orders.returns.index')->with('success', __('messages.order_return_created'));
    }

    public function show(OrderReturn $orderReturn)
    {
        $orderReturn->load('order.customer', 'items.product.unit');
        $company = [
            'name' => \App\Models\Setting::get('company_name', 'My Business'),
            'address' => \App\Models\Setting::get('company_address', ''),
            'phone' => \App\Models\Setting::get('company_phone', ''),
            'tax_id' => \App\Models\Setting::get('tax_id', ''),
        ];
        return view('orders.returns.show', compact('orderReturn', 'company'));
    }

    public function destroy(OrderReturn $orderReturn)
    {
        foreach ($orderReturn->items as $item) {
            $item->product->decrement('stock', $item->quantity);
        }
        $orderReturn->delete();
        return redirect()->route('orders.returns.index')->with('success', __('messages.order_return_deleted'));
    }
}
