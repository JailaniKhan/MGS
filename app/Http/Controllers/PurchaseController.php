<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchasePayment;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Services\Billing\BillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::with(['customer', 'supplier'])
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get();
        $customers = Customer::orderBy('name')->get();
        $products = Product::with('category', 'unit')->orderBy('name')->get();
        $defaultTaxRate = Setting::get('default_tax_rate', '0');
        $defaultTaxType = Setting::get('default_tax_type', 'exclusive');
        return view('purchases.create', compact('suppliers', 'customers', 'products', 'defaultTaxRate', 'defaultTaxType'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person' => ['required', 'string', 'regex:/^(customer|supplier):\d+$/'],
            'currency' => 'required|in:AFN,USD',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_type' => 'nullable|in:inclusive,exclusive',
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

        $taxRate = $validated['tax_rate'] ?? 0;
        $taxType = $validated['tax_type'] ?? 'exclusive';
        $taxAmount = '0.00';
        $totalAmount = $subtotal;

        if ($taxRate > 0) {
            if ($taxType === 'exclusive') {
                $taxAmount = bcmul($subtotal, bcdiv((string) $taxRate, '100', 6), 2);
                $totalAmount = bcadd($subtotal, $taxAmount, 2);
            } else {
                $totalAmount = $subtotal;
                $taxAmount = bcmul($subtotal, bcdiv((string) $taxRate, bcadd('100', (string) $taxRate, 6), 6), 2);
                $subtotal = bcsub($totalAmount, $taxAmount, 2);
            }
        }

        $purchase = Purchase::create([
            'person_type' => $personType,
            'person_id' => $personId,
            'supplier_id' => $personType === 'supplier' ? $personId : null,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_type' => $taxType,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'currency' => $validated['currency'],
            'status' => 'pending',
        ]);

        $purchase->purchaseItems()->createMany($purchaseItems);

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
            'tax_id' => Setting::get('tax_id', ''),
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
            'tax_id' => Setting::get('tax_id', ''),
        ];
        $billPrefix = Setting::get('purchase_prefix', 'PUR-');
        $billNumber = $billPrefix . $purchase->id;
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
        if (!in_array($status, $allowed, true)) {
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
