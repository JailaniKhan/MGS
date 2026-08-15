<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AddsListBalances;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\Billing\BillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseController extends Controller
{
    use AddsListBalances;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $purchasesQuery = Purchase::with(['customer', 'supplier']);

        if ($search !== '') {
            if (ctype_digit($search)) {
                $purchasesQuery->where('purchases.id', (int) $search);
            } else {
                $purchasesQuery->where(function ($q) use ($search) {
                    $q->where(fn ($q) => $q->where('person_type', 'supplier')
                        ->whereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
                    $q->orWhere(fn ($q) => $q->where('person_type', 'customer')
                        ->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
                });
            }
        }

        $purchases = $purchasesQuery
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // One grouped query instead of the accessors' ~4 queries per row.
        $this->attachPurchaseListBalances($purchases->getCollection());

        return view('purchases.index', compact('purchases', 'search'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get();
        $customers = Customer::orderBy('name')->get();
        $products = Product::with('category', 'unit')->orderBy('name')->get();

        $personOptions = collect($suppliers)
            ->map(fn ($supplier) => [
                'value' => 'supplier:'.$supplier->id,
                'label' => $supplier->name,
                'sublabel' => $supplier->phone,
                'group' => __('messages.suppliers'),
            ])
            ->concat($customers->map(fn ($customer) => [
                'value' => 'customer:'.$customer->id,
                'label' => $customer->name,
                'sublabel' => $customer->phone,
                'group' => __('messages.customers'),
            ]))
            ->values()
            ->all();

        $productOptions = $products
            ->map(fn ($product) => [
                'value' => $product->id,
                'label' => $product->name,
                'sublabel' => __('messages.stock').': '.$product->stock.($product->unit ? ' '.($product->unit->short_name ?? $product->unit->name) : ''),
                'price' => number_format((float) $product->price),
                'lot' => $product->lot_number,
            ])
            ->values()
            ->all();

        return view('purchases.create', compact('suppliers', 'customers', 'products', 'personOptions', 'productOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person' => ['required', 'string', 'regex:/^(customer|supplier):\d+$/'],
            'currency' => 'required|in:AFN,USD',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.lot_number' => 'nullable|string|max:255',
        ]);

        [$personType, $personId] = explode(':', $validated['person'], 2);
        $personId = (int) $personId;

        if ($personType === 'customer') {
            abort_if(! Customer::where('id', $personId)->exists(), 404);
        } else {
            abort_if(! Supplier::where('id', $personId)->exists(), 404);
        }

        $subtotal = '0.00';
        $purchaseItems = [];

        foreach ($validated['products'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $lineTotal = bcmul((string) $item['unit_price'], (string) $item['quantity'], 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);

            $purchaseItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $lineTotal,
                'lot_number' => $item['lot_number'] ?? null,
            ];

            // Increase stock when purchasing
            $product->increment('stock', $item['quantity']);
        }

        $purchase = Purchase::create([
            'person_type' => $personType,
            'person_id' => $personId,
            'supplier_id' => $personType === 'supplier' ? $personId : null,
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'currency' => $validated['currency'],
            'status' => 'pending',
        ]);

        $purchase->purchaseItems()->createMany($purchaseItems);

        // A purchase with a lot number sets the product's current lot forward.
        foreach ($purchaseItems as $item) {
            if (! empty($item['lot_number'])) {
                Product::where('id', $item['product_id'])
                    ->where('lot_number', '<>', $item['lot_number'])
                    ->update(['lot_number' => $item['lot_number']]);
            }
        }

        foreach ($purchaseItems as $item) {
            StockMovement::create([
                'user_id' => Auth::id(),
                'product_id' => $item['product_id'],
                'quantity_change' => $item['quantity'],
                'movement_type' => 'purchase',
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
                'notes' => __('messages.purchase'),
            ]);
        }

        return redirect()->route('purchases.index')->with('success', __('messages.purchase_created'));
    }

    public function show(Purchase $purchase)
    {
        $purchase->load('supplier', 'purchaseItems.product.unit', 'purchasePayments');
        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
        ];

        return view('purchases.show', compact('purchase', 'company'));
    }

    public function print(Purchase $purchase)
    {
        $purchase->load('supplier', 'purchaseItems.product.unit', 'purchasePayments');
        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
            'email' => Setting::get('company_email', ''),
        ];
        $billPrefix = Setting::get('purchase_prefix', 'PUR-');
        $billNumber = $billPrefix.$purchase->id;

        return view('purchases.print', compact('purchase', 'company', 'billNumber'));
    }

    public function sendWhatsApp(Purchase $purchase, BillService $bills)
    {
        $bill = $bills->purchaseBill($purchase);

        $result = $bills->send(
            $purchase->party,
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

    public function status(Purchase $purchase, $status)
    {
        $allowed = ['pending', 'processing', 'completed', 'cancelled'];
        if (! in_array($status, $allowed, true)) {
            return back()->with('error', __('messages.invalid_status'));
        }

        if ($purchase->status === 'cancelled' && $status !== 'cancelled') {
            return back()->with('error', __('messages.invalid_status'));
        }

        if ($status === 'cancelled' && $purchase->status !== 'cancelled') {
            \DB::transaction(function () use ($purchase) {
                foreach ($purchase->purchaseItems as $item) {
                    $product = $item->product()->lockForUpdate()->first();
                    $product->decrement('stock', $item->quantity);

                    StockMovement::create([
                        'user_id' => Auth::id(),
                        'product_id' => $item->product_id,
                        'quantity_change' => -$item->quantity,
                        'movement_type' => 'purchase_cancelled',
                        'reference_type' => 'purchase',
                        'reference_id' => $purchase->id,
                        'notes' => __('messages.purchase_cancelled'),
                    ]);
                }
            });
        }

        $purchase->update(['status' => $status]);

        return redirect()->route('purchases.show', $purchase)->with('success', __('messages.purchase_status_changed'));
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

        if ($validated['currency'] !== $purchase->currency) {
            return back()->with('error', __('messages.payment_currency_mismatch'));
        }

        PurchasePayment::create($validated);

        return redirect()->route('purchases.show', $purchase)->with('success', __('messages.payment_created'));
    }

    public function destroy(Purchase $purchase)
    {
        \DB::transaction(function () use ($purchase) {
            foreach ($purchase->purchaseItems as $item) {
                $product = $item->product()->lockForUpdate()->first();
                $product->decrement('stock', $item->quantity);

                // Log stock movement so audit trail exists for the deletion.
                StockMovement::create([
                    'user_id' => Auth::id(),
                    'product_id' => $item->product_id,
                    'quantity_change' => -$item->quantity,
                    'movement_type' => 'purchase_deleted',
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                    'notes' => __('messages.purchase_deleted'),
                ]);
            }
            $purchase->delete();
        });

        return redirect()->route('purchases.index')->with('success', __('messages.purchase_deleted'));
    }
}
