<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AddsListBalances;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\Billing\BillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use AddsListBalances;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $ordersQuery = Order::with(['customer', 'supplier']);
        $purchasesQuery = Purchase::with(['customer', 'supplier']);

        if ($search !== '') {
            if (ctype_digit($search)) {
                // Pure digits mean a document id — never fuzzy-match names or
                // phones, or a search for "1" also matches every phone
                // containing the digit.
                $ordersQuery->where('orders.id', (int) $search);
                $purchasesQuery->where('purchases.id', (int) $search);
            } else {
                $ordersQuery->where(function ($q) use ($search) {
                    $q->where(fn ($q) => $q->where('person_type', 'customer')
                        ->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
                    $q->orWhere(fn ($q) => $q->where('person_type', 'supplier')
                        ->whereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
                });
                $purchasesQuery->where(function ($q) use ($search) {
                    $q->where(fn ($q) => $q->where('person_type', 'customer')
                        ->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
                    $q->orWhere(fn ($q) => $q->where('person_type', 'supplier')
                        ->whereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
                });
            }
        }

        $orders = $ordersQuery
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE, ['*'], 'orders_page')
            ->withQueryString();

        $purchases = $purchasesQuery
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE, ['*'], 'purchases_page')
            ->withQueryString();

        // One grouped query per side for remaining/list_status — the model
        // accessors would run ~4 queries per row (was ~160 queries/page).
        $this->attachOrderListBalances($orders->getCollection());
        $this->attachPurchaseListBalances($purchases->getCollection());

        // Month summary tiles (single pass per side, split by currency).
        $monthStart = now()->startOfMonth();
        $orderMonth = Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $monthStart)->get(['total_amount', 'currency']);
        $orderMonthAFN = $orderMonth->where('currency', 'AFN')->sum('total_amount');
        $orderMonthUSD = $orderMonth->where('currency', 'USD')->sum('total_amount');

        $purchaseMonth = Purchase::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $monthStart)->get(['total_amount', 'currency']);
        $purchaseMonthAFN = $purchaseMonth->where('currency', 'AFN')->sum('total_amount');
        $purchaseMonthUSD = $purchaseMonth->where('currency', 'USD')->sum('total_amount');

        return view('orders.index', compact(
            'orders', 'purchases', 'search',
            'orderMonthAFN', 'orderMonthUSD',
            'purchaseMonthAFN', 'purchaseMonthUSD'
        ));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::with('category', 'unit')->where('stock', '>', 0)->orderBy('name')->get();

        $productLots = PurchaseItem::whereNotNull('lot_number')
            ->where('lot_number', '<>', '')
            ->get(['product_id', 'lot_number'])
            ->groupBy('product_id')
            ->map->pluck('lot_number')
            ->map->unique()
            ->map->values()
            ->toArray();

        $personOptions = collect($customers)
            ->map(fn ($customer) => [
                'value' => 'customer:'.$customer->id,
                'label' => $customer->name,
                'sublabel' => $customer->phone,
                'group' => __('messages.customers'),
            ])
            ->concat($suppliers->map(fn ($supplier) => [
                'value' => 'supplier:'.$supplier->id,
                'label' => $supplier->name,
                'sublabel' => $supplier->phone,
                'group' => __('messages.suppliers'),
            ]))
            ->values()
            ->all();

        $productOptions = $products
            ->map(function ($product) {
                // A lot is priced in one currency; surface that price (and its
                // currency) so the form only autofills on a matching side.
                $usd = (float) $product->price <= 0 && (float) ($product->price_usd ?? 0) > 0;

                return [
                    'value' => $product->id,
                    'label' => $product->name,
                    'sublabel' => __('messages.afn').': '.$product->stock_afn.' · '.__('messages.usd').': '.$product->stock_usd.($product->unit ? ' '.($product->unit->short_name ?? $product->unit->name) : ''),
                    'price' => number_format((float) ($usd ? $product->price_usd : $product->price), 2, '.', ''),
                    'price_currency' => $usd ? 'USD' : 'AFN',
                    'lot' => $product->lot_number,
                ];
            })
            ->values()
            ->all();

        return view('orders.create', compact('customers', 'suppliers', 'products', 'productLots', 'personOptions', 'productOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person' => 'required|string',
            'currency' => 'required|in:AFN,USD',
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
        $order = null;

        DB::transaction(function () use ($validated, $orderItems, $subtotal, $customerId, $personType, $personId, &$order) {
            foreach ($validated['products'] as $item) {
                $product = Product::where('id', $item['product_id'])->lockForUpdate()->firstOrFail();

                // Stock pools are per currency: a USD sale draws from stock_usd
                // even when the AFN pool is full.
                if (! $product->hasStockFor($validated['currency'], (int) $item['quantity'])) {
                    throw ValidationException::withMessages([
                        'products' => __('messages.insufficient_stock').' د '.$product->name.'! ('.$validated['currency'].': '.$product->stockFor($validated['currency']).')',
                    ]);
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

                $product->moveStock($validated['currency'], -(int) $item['quantity']);
            }

            $order = Order::create([
                'customer_id' => $customerId,
                'person_type' => $personType,
                'person_id' => $personId,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'currency' => $validated['currency'],
            ]);

            $order->orderItems()->createMany($orderItems);

            // Record stock movements after order is created so we have the reference ID
            foreach ($orderItems as $item) {
                StockMovement::create([
                    'user_id' => Auth::id(),
                    'product_id' => $item['product_id'],
                    'quantity_change' => -$item['quantity'],
                    'currency' => $validated['currency'],
                    'movement_type' => 'sale',
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'notes' => __('messages.sale'),
                ]);
            }
        });

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
        ];
        $invoicePrefix = Setting::get('invoice_prefix', 'INV-');
        $invoiceNumber = $invoicePrefix.$order->id;

        return view('orders.print', compact('order', 'company', 'invoiceNumber'));
    }

    public function sendWhatsApp(Order $order, BillService $bills)
    {
        $bill = $bills->orderBill($order);

        $result = $bills->send(
            $order->party,
            $bill['phone'],
            $bill['message'],
            $bill['amount'],
            $bill['currency'],
        );

        $message = $result['ok']
            ? __('messages.bill_sent', ['phone' => $bill['phone']])
            : $result['error'];

        return back()->with($result['ok'] ? 'success' : 'error', $message);
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

        // A cancelled order has returned its stock to the shelf; allowing it
        // back to a live status would desync inventory (same rule as status()).
        if ($order->status === 'cancelled' && $validated['status'] !== 'cancelled') {
            return back()->with('error', __('messages.invalid_status'));
        }

        if ($validated['status'] === 'cancelled' && $order->status !== 'cancelled') {
            $this->cancelAndRestoreStock($order);
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
        if (! in_array($status, $allowed, true)) {
            return back()->with('error', __('messages.invalid_status'));
        }

        if ($order->status === 'cancelled' && $status !== 'cancelled') {
            return back()->with('error', __('messages.invalid_status'));
        }

        if ($status === 'cancelled' && $order->status !== 'cancelled') {
            $this->cancelAndRestoreStock($order);
        }

        $order->update(['status' => $status]);

        return redirect()->route('orders.index')->with('success', __('messages.order_status_changed'));
    }

    /**
     * Return a live order's stock to the shelf and record one
     * order_cancelled movement per line — the single implementation shared
     * by update() and status().
     */
    private function cancelAndRestoreStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->orderItems as $item) {
                // Lock the product row so concurrent updates don't race.
                $product = $item->product()->lockForUpdate()->first();
                $product->moveStock($order->currency, (int) $item->quantity);

                StockMovement::create([
                    'user_id' => Auth::id(),
                    'product_id' => $item->product_id,
                    'quantity_change' => $item->quantity,
                    'currency' => $order->currency,
                    'movement_type' => 'order_cancelled',
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'notes' => __('messages.order_cancelled'),
                ]);
            }
        });
    }

    public function destroy(Order $order)
    {
        \DB::transaction(function () use ($order) {
            // A cancelled order already returned its stock on cancellation —
            // restoring it again here would double-count inventory.
            if ($order->status !== 'cancelled') {
                foreach ($order->orderItems as $item) {
                    $product = $item->product()->lockForUpdate()->first();
                    $product->moveStock($order->currency, (int) $item->quantity);

                    StockMovement::create([
                        'user_id' => Auth::id(),
                        'product_id' => $item->product_id,
                        'quantity_change' => $item->quantity,
                        'currency' => $order->currency,
                        'movement_type' => 'order_deleted',
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'notes' => __('messages.order_deleted'),
                    ]);
                }
            }
            $order->delete();
        });

        return redirect()->route('orders.index')->with('success', __('messages.order_deleted'));
    }
}
