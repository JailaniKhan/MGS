<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchasePayment;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::with('supplier', 'purchaseItems.product.unit')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::with('category', 'unit')->orderBy('name')->get();
        return view('purchases.create', compact('suppliers', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'currency' => 'required|in:AFN,USD',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
        ]);

        $totalAmount = 0;
        $purchaseItems = [];

        foreach ($validated['products'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $subtotal = $item['unit_price'] * $item['quantity'];
            $totalAmount += $subtotal;

            $purchaseItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $subtotal,
            ];

            // Increase stock when purchasing
            $product->increment('stock', $item['quantity']);
        }

        $purchase = Purchase::create([
            'supplier_id' => $validated['supplier_id'],
            'total_amount' => $totalAmount,
            'currency' => $validated['currency'],
            'status' => 'pending',
        ]);

        $purchase->purchaseItems()->createMany($purchaseItems);

        return redirect()->route('purchases.index')->with('success', 'خرید په بریالیتوب سره ترسره شو!');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load('supplier', 'purchaseItems.product.unit', 'purchasePayments');
        return view('purchases.show', compact('purchase'));
    }

    public function status(Purchase $purchase, $status)
    {
        $allowed = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($status, $allowed)) {
            return back()->with('error', 'ناقص حالت!');
        }

        $purchase->update(['status' => $status]);
        return redirect()->route('purchases.show', $purchase)->with('success', 'د خرید حالت بدل شو!');
    }

    public function paymentStore(Request $request)
    {
        $validated = $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'notes' => 'nullable|string|max:500',
        ]);

        $purchase = Purchase::findOrFail($validated['purchase_id']);

        PurchasePayment::create($validated);

        return redirect()->route('purchases.show', $purchase)->with('success', 'پیسې په بریالیتوب سره ثبت شوې!');
    }

    public function destroy(Purchase $purchase)
    {
        foreach ($purchase->purchaseItems as $item) {
            $item->product->decrement('stock', $item->quantity);
        }
        $purchase->delete();
        return redirect()->route('purchases.index')->with('success', 'خرید په بریالیتوب سره ړنګ شو!');
    }
}
