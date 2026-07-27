<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReturn;
use App\Models\Purchase;
use App\Models\PurchaseReturnItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $purchaseReturns = PurchaseReturn::with(['purchase.supplier', 'purchase.customer', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('purchases.returns.index', compact('purchaseReturns'));
    }

    public function create()
    {
        $purchases = Purchase::with('purchaseItems.product')->orderBy('created_at', 'desc')->get();
        return view('purchases.returns.create', compact('purchases'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'return_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
            'status' => 'required|in:pending,processing,completed,cancelled',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
        ]);

        $purchase = Purchase::with('purchaseItems.product')->findOrFail($validated['purchase_id']);

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

            $product->decrement('stock', $item['quantity']);
        }

        $purchaseReturn = PurchaseReturn::create([
            'purchase_id' => $validated['purchase_id'],
            'supplier_id' => $purchase->supplier_id,
            'return_date' => $validated['return_date'],
            'reason' => $validated['reason'],
            'total_amount' => $subtotal,
            'status' => $validated['status'],
            'currency' => $purchase->currency,
            'user_id' => Auth::id(),
        ]);

        $purchaseReturn->items()->createMany($returnItems);

        foreach ($returnItems as $item) {
            StockMovement::create([
                'user_id' => Auth::id(),
                'product_id' => $item['product_id'],
                'quantity_change' => -$item['quantity'],
                'movement_type' => 'purchase_return',
                'reference_type' => 'purchase_return',
                'reference_id' => $purchaseReturn->id,
                'notes' => __('messages.purchase_return'),
            ]);
        }

        return redirect()->route('purchases.returns.index')->with('success', __('messages.purchase_return_created'));
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load('purchase.supplier', 'items.product.unit');
        $company = [
            'name' => \App\Models\Setting::get('company_name', 'My Business'),
            'address' => \App\Models\Setting::get('company_address', ''),
            'phone' => \App\Models\Setting::get('company_phone', ''),
            'tax_id' => \App\Models\Setting::get('tax_id', ''),
        ];
        return view('purchases.returns.show', compact('purchaseReturn', 'company'));
    }

    public function destroy(PurchaseReturn $purchaseReturn)
    {
        foreach ($purchaseReturn->items as $item) {
            $item->product->increment('stock', $item->quantity);

            StockMovement::create([
                'user_id' => Auth::id(),
                'product_id' => $item->product_id,
                'quantity_change' => $item->quantity,
                'movement_type' => 'purchase_return_cancelled',
                'reference_type' => 'purchase_return',
                'reference_id' => $purchaseReturn->id,
                'notes' => __('messages.purchase_return_cancelled'),
            ]);
        }
        $purchaseReturn->delete();
        return redirect()->route('purchases.returns.index')->with('success', __('messages.purchase_return_deleted'));
    }
}
