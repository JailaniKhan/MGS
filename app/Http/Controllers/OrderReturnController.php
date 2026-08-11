<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\StockMovement;
use App\Services\Sales\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderReturnController extends Controller
{
    public function index()
    {
        $orderReturns = OrderReturn::with(['order.customer', 'order.supplier', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('orders.returns.index', compact('orderReturns'));
    }

    public function create()
    {
        $orders = Order::with('orderItems.product')->orderBy('created_at', 'desc')->get();

        return view('orders.returns.create', compact('orders'));
    }

    public function store(Request $request, ReturnService $returnService)
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

        $returnService->createReturn(
            direction: 'order',
            parent: $order,
            items: $validated['products'],
            returnDate: $validated['return_date'],
            reason: $validated['reason'] ?? null,
            status: $validated['status'],
        );

        return redirect()->route('orders.returns.index')->with('success', __('messages.order_return_created'));
    }

    public function show(OrderReturn $orderReturn)
    {
        $orderReturn->load('order.customer', 'items.product.unit');
        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
        ];

        return view('orders.returns.show', compact('orderReturn', 'company'));
    }

    public function destroy(OrderReturn $orderReturn)
    {
        foreach ($orderReturn->items as $item) {
            $item->product->decrement('stock', $item->quantity);

            StockMovement::create([
                'user_id' => Auth::id(),
                'product_id' => $item->product_id,
                'quantity_change' => -$item->quantity,
                'movement_type' => 'return_cancelled',
                'reference_type' => 'order_return',
                'reference_id' => $orderReturn->id,
                'notes' => __('messages.return_cancelled'),
            ]);
        }
        $orderReturn->delete();

        return redirect()->route('orders.returns.index')->with('success', __('messages.order_return_deleted'));
    }
}
