<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Setting;
use App\Services\Sales\ReturnService;
use Illuminate\Http\Request;

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

    public function store(Request $request, ReturnService $returnService)
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

        $returnService->createReturn(
            direction: 'purchase',
            parent: $purchase,
            items: $validated['products'],
            returnDate: $validated['return_date'],
            reason: $validated['reason'] ?? null,
            status: $validated['status'],
        );

        return redirect()->route('purchases.returns.index')->with('success', __('messages.purchase_return_created'));
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load('purchase.supplier', 'items.product.unit');
        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
        ];

        return view('purchases.returns.show', compact('purchaseReturn', 'company'));
    }

    public function destroy(PurchaseReturn $purchaseReturn, ReturnService $returnService)
    {
        $returnService->revertReturn($purchaseReturn);

        return redirect()->route('purchases.returns.index')->with('success', __('messages.purchase_return_cancelled'));
    }
}
