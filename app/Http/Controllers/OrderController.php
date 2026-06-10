<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('customer', 'orderItems.product')->orderBy('created_at', 'desc')->get();
        return view('orders.index', compact('orders'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $products = Product::with('category')->where('stock', '>', 0)->orderBy('name')->get();
        return view('orders.create', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        $totalAmount = 0;
        $orderItems = [];

        foreach ($validated['products'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            
            if ($product->stock < $item['quantity']) {
                return back()->with('error', "د {$product->name} کافي موجودي نشته! (پاتې: {$product->stock})");
            }

            $subtotal = $product->price * $item['quantity'];
            $totalAmount += $subtotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
                'subtotal' => $subtotal,
            ];

            $product->decrement('stock', $item['quantity']);
        }

        $order = Order::create([
            'customer_id' => $validated['customer_id'],
            'status' => 'pending',
            'total_amount' => $totalAmount,
        ]);

        $order->orderItems()->createMany($orderItems);

        return redirect()->route('orders.index')->with('success', 'امر په بریالیتوب سره جوړ شو!');
    }

    public function show(Order $order)
    {
        $order->load('customer', 'orderItems.product', 'payments');
        return view('orders.show', compact('order'));
    }

    public function edit(Order $order)
    {
        $customers = Customer::orderBy('name')->get();
        return view('orders.edit', compact('order', 'customers'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);

        $order->update($validated);

        return redirect()->route('orders.index')->with('success', 'امر په بریالیتوب سره سم شو!');
    }

    public function status(Order $order, $status)
    {
        $allowed = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($status, $allowed)) {
            return back()->with('error', 'ناقص حالت!');
        }

        if ($status === 'cancelled' && $order->status !== 'cancelled') {
            foreach ($order->orderItems as $item) {
                $item->product->increment('stock', $item->quantity);
            }
        }

        $order->update(['status' => $status]);
        return redirect()->route('orders.index')->with('success', 'د امر حالت بدل شو!');
    }

    public function destroy(Order $order)
    {
        foreach ($order->orderItems as $item) {
            $item->product->increment('stock', $item->quantity);
        }
        $order->delete();
        return redirect()->route('orders.index')->with('success', 'امر په بریالیتوب سره ړنګ شو!');
    }

    public function getProductPrice(Product $product)
    {
        return response()->json(['price' => $product->price, 'stock' => $product->stock]);
    }
}