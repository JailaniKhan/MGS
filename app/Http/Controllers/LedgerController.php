<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index()
    {
        $customers = Customer::withCount('orders')
            ->withSum('orders', 'total_amount')
            ->orderBy('name')
            ->get()
            ->map(function ($customer) {
                // Get payments made through the regular payment flow
                $paymentsFromOrdersAFN = Payment::whereHas('order', function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id);
                })->where('currency', 'AFN')->sum('amount');
                
                $paymentsFromOrdersUSD = Payment::whereHas('order', function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id);
                })->where('currency', 'USD')->sum('amount');

                // Get payments made through the ledger
                $ledgerPaymentsAFN = LedgerEntry::where('person_type', 'customer')
                    ->where('person_id', $customer->id)
                    ->where('currency', 'AFN')
                    ->where('type', 'payment_received')
                    ->sum('amount');
                    
                $ledgerPaymentsUSD = LedgerEntry::where('person_type', 'customer')
                    ->where('person_id', $customer->id)
                    ->where('currency', 'USD')
                    ->where('type', 'payment_received')
                    ->sum('amount');

                $paidAFN = $paymentsFromOrdersAFN + $ledgerPaymentsAFN;
                $paidUSD = $paymentsFromOrdersUSD + $ledgerPaymentsUSD;

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'type' => 'customer',
                    'type_label' => 'ګیراک',
                    'total_orders' => $customer->orders_count,
                    'total_amount_afn' => $customer->orders()->where('currency', 'AFN')->sum('total_amount'),
                    'total_amount_usd' => $customer->orders()->where('currency', 'USD')->sum('total_amount'),
                    'paid_afn' => $paidAFN,
                    'paid_usd' => $paidUSD,
                    'remaining_afn' => max(0, $customer->orders()->where('currency', 'AFN')->sum('total_amount') - $paidAFN),
                    'remaining_usd' => max(0, $customer->orders()->where('currency', 'USD')->sum('total_amount') - $paidUSD),
                ];
            });

        $suppliers = Supplier::withCount('purchases')
            ->withSum('purchases', 'total_amount')
            ->orderBy('name')
            ->get()
            ->map(function ($supplier) {
                // Get payments made through the regular purchase payment flow
                $paymentsFromPurchasesAFN = PurchasePayment::whereHas('purchase', function ($q) use ($supplier) {
                    $q->where('supplier_id', $supplier->id);
                })->where('currency', 'AFN')->sum('amount');
                
                $paymentsFromPurchasesUSD = PurchasePayment::whereHas('purchase', function ($q) use ($supplier) {
                    $q->where('supplier_id', $supplier->id);
                })->where('currency', 'USD')->sum('amount');

                // Get payments made through the ledger
                $ledgerPaymentsAFN = LedgerEntry::where('person_type', 'supplier')
                    ->where('person_id', $supplier->id)
                    ->where('currency', 'AFN')
                    ->where('type', 'payment_made')
                    ->sum('amount');
                    
                $ledgerPaymentsUSD = LedgerEntry::where('person_type', 'supplier')
                    ->where('person_id', $supplier->id)
                    ->where('currency', 'USD')
                    ->where('type', 'payment_made')
                    ->sum('amount');

                $paidAFN = $paymentsFromPurchasesAFN + $ledgerPaymentsAFN;
                $paidUSD = $paymentsFromPurchasesUSD + $ledgerPaymentsUSD;

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'phone' => $supplier->phone,
                    'type' => 'supplier',
                    'type_label' => 'پلورونکی',
                    'total_orders' => $supplier->purchases_count,
                    'total_amount_afn' => $supplier->purchases()->where('currency', 'AFN')->sum('total_amount'),
                    'total_amount_usd' => $supplier->purchases()->where('currency', 'USD')->sum('total_amount'),
                    'paid_afn' => $paidAFN,
                    'paid_usd' => $paidUSD,
                    'remaining_afn' => max(0, $supplier->purchases()->where('currency', 'AFN')->sum('total_amount') - $paidAFN),
                    'remaining_usd' => max(0, $supplier->purchases()->where('currency', 'USD')->sum('total_amount') - $paidUSD),
                ];
            });

        $people = collect($customers)->concat($suppliers)->sortBy('name')->values();

        return view('ledger.index', compact('people'));
    }

    public function show($type, $id)
    {
        if ($type === 'customer') {
            $person = Customer::findOrFail($id);
            $personType = 'customer';
            $personLabel = 'ګیراک';
            $totalAFN = $person->orders()->where('currency', 'AFN')->sum('total_amount');
            $totalUSD = $person->orders()->where('currency', 'USD')->sum('total_amount');
            
            // Get payments from both sources
            $paymentsFromOrdersAFN = Payment::whereHas('order', function ($q) use ($person) {
                $q->where('customer_id', $person->id);
            })->where('currency', 'AFN')->sum('amount');
            
            $paymentsFromOrdersUSD = Payment::whereHas('order', function ($q) use ($person) {
                $q->where('customer_id', $person->id);
            })->where('currency', 'USD')->sum('amount');

            $ledgerPaymentsAFN = LedgerEntry::where('person_type', 'customer')
                ->where('person_id', $id)
                ->where('currency', 'AFN')
                ->where('type', 'payment_received')
                ->sum('amount');
                
            $ledgerPaymentsUSD = LedgerEntry::where('person_type', 'customer')
                ->where('person_id', $id)
                ->where('currency', 'USD')
                ->where('type', 'payment_received')
                ->sum('amount');

            $paidAFN = $paymentsFromOrdersAFN + $ledgerPaymentsAFN;
            $paidUSD = $paymentsFromOrdersUSD + $ledgerPaymentsUSD;

        } elseif ($type === 'supplier') {
            $person = Supplier::findOrFail($id);
            $personType = 'supplier';
            $personLabel = 'پلورونکی';
            $totalAFN = $person->purchases()->where('currency', 'AFN')->sum('total_amount');
            $totalUSD = $person->purchases()->where('currency', 'USD')->sum('total_amount');
            
            // Get payments from both sources
            $paymentsFromPurchasesAFN = PurchasePayment::whereHas('purchase', function ($q) use ($person) {
                $q->where('supplier_id', $person->id);
            })->where('currency', 'AFN')->sum('amount');
            
            $paymentsFromPurchasesUSD = PurchasePayment::whereHas('purchase', function ($q) use ($person) {
                $q->where('supplier_id', $person->id);
            })->where('currency', 'USD')->sum('amount');

            $ledgerPaymentsAFN = LedgerEntry::where('person_type', 'supplier')
                ->where('person_id', $id)
                ->where('currency', 'AFN')
                ->where('type', 'payment_made')
                ->sum('amount');
                
            $ledgerPaymentsUSD = LedgerEntry::where('person_type', 'supplier')
                ->where('person_id', $id)
                ->where('currency', 'USD')
                ->where('type', 'payment_made')
                ->sum('amount');

            $paidAFN = $paymentsFromPurchasesAFN + $ledgerPaymentsAFN;
            $paidUSD = $paymentsFromPurchasesUSD + $ledgerPaymentsUSD;

        } else {
            abort(404);
        }

        $entries = LedgerEntry::where('person_type', $personType)
            ->where('person_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('ledger.show', compact(
            'person', 'personType', 'personLabel',
            'totalAFN', 'totalUSD',
            'paidAFN', 'paidUSD',
            'entries'
        ));
    }

    public function paymentStore(Request $request, $type, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'notes' => 'nullable|string|max:500',
        ]);

        $entryType = $type === 'customer' ? 'payment_received' : 'payment_made';

        LedgerEntry::create([
            'person_type' => $type === 'customer' ? 'customer' : 'supplier',
            'person_id' => $id,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'type' => $entryType,
            'notes' => $validated['notes'],
        ]);

        $personLabel = $type === 'customer' ? 'ګیراک' : 'پلورونکی';
        return redirect()->route('ledger.show', [$type, $id])
            ->with('success', "د {$personLabel} لپاره پیسې په بریالیتوب سره ثبت شوې!");
    }
}