<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('customer', 'orderItems.product')->orderBy('created_at', 'desc')->get();
        $purchases = \App\Models\Purchase::with('supplier', 'purchaseItems.product.unit', 'purchasePayments')
            ->orderBy('created_at', 'desc')->get();
        return view('orders.index', compact('orders', 'purchases'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::with('category', 'unit')->where('stock', '>', 0)->orderBy('name')->get();

        $productLots = \App\Models\PurchaseItem::whereNotNull('lot_number')
            ->where('lot_number', '<>', '')
            ->get(['product_id', 'lot_number'])
            ->groupBy('product_id')
            ->map->pluck('lot_number')
            ->map->unique()
            ->map->values()
            ->toArray();

        $defaultTaxRate = Setting::get('default_tax_rate', '0');
        $defaultTaxType = Setting::get('default_tax_type', 'exclusive');
        return view('orders.create', compact('customers', 'suppliers', 'products', 'productLots', 'defaultTaxRate', 'defaultTaxType'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person' => 'required|string',
            'currency' => 'required|in:AFN,USD',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_type' => 'nullable|in:inclusive,exclusive',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric|min:0.01',
            'products.*.lot_number' => 'nullable|string|max:255',
        ]);

        // The `person` field carries both customers and suppliers, e.g. "customer:5" / "supplier:3".
        [$personType, $personId] = array_pad(explode(':', $validated['person'], 2), 2, null);

        if ($personType === 'supplier') {
            $party = Supplier::findOrFail($personId);
            $customerId = null;
        } else {
            $personType = 'customer';
            $party = Customer::findOrFail($personId);
            $customerId = $personId;
        }

        $subtotal = '0.00';
        $orderItems = [];

        foreach ($validated['products'] as $item) {
            $product = Product::findOrFail($item['product_id']);

            if ($product->stock < $item['quantity']) {
                return back()->with('error', "د {$product->name} __('messages.insufficient_stock')! (__('messages.pending'): {$product->stock})");
            }

            $unitPrice = (string) $item['unit_price'];
            $lineTotal = bcmul($unitPrice, (string) $item['quantity'], 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'subtotal' => $lineTotal,
                'lot_number' => $item['lot_number'] ?? null,
            ];

            $product->decrement('stock', $item['quantity']);
        }

        $taxRate = $validated['tax_rate'] ?? 0;
        $taxType = $validated['tax_type'] ?? 'exclusive';
        $taxAmount = 0;
        $totalAmount = $subtotal;

        if ($taxRate > 0) {
            if ($taxType === 'exclusive') {
                $taxAmount = bcmul($subtotal, bcdiv((string) $taxRate, '100', 6), 2);
                $totalAmount = bcadd($subtotal, $taxAmount, 2);
            } else {
                // Inclusive: tax is included in price, so extract it
                $totalAmount = $subtotal;
                $taxAmount = bcmul($subtotal, bcdiv((string) $taxRate, bcadd('100', (string) $taxRate, 6), 6), 2);
                $subtotal = bcsub($totalAmount, $taxAmount, 2);
            }
        }

        $order = Order::create([
            'customer_id' => $customerId,
            'person_type' => $personType,
            'person_id' => $personId,
            'status' => 'pending',
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_type' => $taxType,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'currency' => $validated['currency'],
        ]);

        $order->orderItems()->createMany($orderItems);

        return redirect()->route('orders.index')->with('success', __('messages.order_created'));
    }

    public function show(Order $order)
    {
        $order->load('customer', 'orderItems.product.unit', 'payments');
        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
            'email' => Setting::get('company_email', ''),
            'tax_id' => Setting::get('tax_id', ''),
        ];
        return view('orders.show', compact('order', 'company'));
    }

    public function print(Order $order)
    {
        $order->load('customer', 'orderItems.product.unit', 'payments');
        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
            'email' => Setting::get('company_email', ''),
            'tax_id' => Setting::get('tax_id', ''),
        ];
        $invoicePrefix = Setting::get('invoice_prefix', 'INV-');
        $invoiceNumber = $invoicePrefix . $order->id;
        return view('orders.print', compact('order', 'company', 'invoiceNumber'));
    }

    public function edit(Order $order)
    {
        $customers = Customer::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        return view('orders.edit', compact('order', 'customers', 'suppliers'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'person' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);

        if ($validated['status'] === 'cancelled' && $order->status !== 'cancelled') {
            foreach ($order->orderItems as $item) {
                $item->product->increment('stock', $item->quantity);
            }
        }

        if (! empty($validated['person'])) {
            [$personType, $personId] = array_pad(explode(':', $validated['person'], 2), 2, null);

            if ($personType === 'supplier') {
                Supplier::findOrFail($personId);
                $order->update([
                    'person_type' => 'supplier',
                    'person_id' => $personId,
                    'customer_id' => null,
                    'status' => $validated['status'],
                ]);
            } else {
                Customer::findOrFail($personId);
                $order->update([
                    'person_type' => 'customer',
                    'person_id' => $personId,
                    'customer_id' => $personId,
                    'status' => $validated['status'],
                ]);
            }
        } else {
            $order->update([
                'customer_id' => $validated['customer_id'],
                'status' => $validated['status'],
            ]);
        }

        return redirect()->route('orders.index')->with('success', __('messages.order_updated'));
    }

    public function status(Order $order, $status)
    {
        $allowed = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($status, $allowed)) {
            return back()->with('error', __('messages.invalid_status'));
        }

        if ($order->status === 'cancelled' && $status !== 'cancelled') {
            return back()->with('error', __('messages.invalid_status'));
        }

        if ($status === 'cancelled' && $order->status !== 'cancelled') {
            foreach ($order->orderItems as $item) {
                $item->product->increment('stock', $item->quantity);
            }
        }

        $order->update(['status' => $status]);
        return redirect()->route('orders.index')->with('success', __('messages.order_status_changed'));
    }

    public function destroy(Order $order)
    {
        foreach ($order->orderItems as $item) {
            $item->product->increment('stock', $item->quantity);
        }
        $order->delete();
        return redirect()->route('orders.index')->with('success', __('messages.order_deleted'));
    }

    public function getProductPrice(Product $product)
    {
        return response()->json(['price' => $product->price, 'stock' => $product->stock]);
    }
}