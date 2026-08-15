<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\PartyPayment;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index()
    {
        // Build a lightweight people list first, paginate it, then summarize only the page.
        $peopleList = Customer::orderBy('name')->get(['id', 'name', 'phone'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'type' => 'customer',
                'type_label' => __('messages.customer'),
            ])
            ->concat(
                Supplier::orderBy('name')->get(['id', 'name', 'phone'])->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'phone' => $s->phone,
                    'type' => 'supplier',
                    'type_label' => __('messages.supplier'),
                ])
            )
            ->sortBy('name')
            ->values();

        $people = $this->paginateCollection($peopleList);

        $people->setCollection(
            $people->getCollection()->map(function (array $person) {
                return $person + $this->summarizePerson($person['type'], $person['id']);
            })
        );

        return view('ledger.index', compact('people'));
    }

    protected function summarizePerson(string $type, int $id): array
    {
        $orderIds = Order::where('person_type', $type)->where('person_id', $id)->pluck('id');
        $purchaseIds = Purchase::where('person_type', $type)->where('person_id', $id)->pluck('id');

        $paymentsFromOrdersAFN = Payment::whereIn('order_id', $orderIds)->where('currency', 'AFN')->sum('amount');
        $paymentsFromOrdersUSD = Payment::whereIn('order_id', $orderIds)->where('currency', 'USD')->sum('amount');
        $paymentsFromPurchasesAFN = PurchasePayment::whereIn('purchase_id', $purchaseIds)->where('currency', 'AFN')->sum('amount');
        $paymentsFromPurchasesUSD = PurchasePayment::whereIn('purchase_id', $purchaseIds)->where('currency', 'USD')->sum('amount');

        $ledgerType = $type === 'customer' ? 'payment_received' : 'payment_made';
        $ledgerPaymentsAFN = PartyPayment::where('person_type', $type)->where('person_id', $id)
            ->where('currency', 'AFN')->where('type', $ledgerType)->sum('amount');
        $ledgerPaymentsUSD = PartyPayment::where('person_type', $type)->where('person_id', $id)
            ->where('currency', 'USD')->where('type', $ledgerType)->sum('amount');

        $paidAFN = $paymentsFromOrdersAFN + $paymentsFromPurchasesAFN + $ledgerPaymentsAFN;
        $paidUSD = $paymentsFromOrdersUSD + $paymentsFromPurchasesUSD + $ledgerPaymentsUSD;

        $totalAFN = Order::whereIn('id', $orderIds)->where('currency', 'AFN')->sum('total_amount')
                    + Purchase::whereIn('id', $purchaseIds)->where('currency', 'AFN')->sum('total_amount');
        $totalUSD = Order::whereIn('id', $orderIds)->where('currency', 'USD')->sum('total_amount')
                    + Purchase::whereIn('id', $purchaseIds)->where('currency', 'USD')->sum('total_amount');

        return [
            'total_documents' => $orderIds->count() + $purchaseIds->count(),
            'total_amount_afn' => $totalAFN,
            'total_amount_usd' => $totalUSD,
            'paid_afn' => $paidAFN,
            'paid_usd' => $paidUSD,
            'remaining_afn' => max(0, $totalAFN - $paidAFN),
            'remaining_usd' => max(0, $totalUSD - $paidUSD),
        ];
    }

    public function show($type, $id)
    {
        if ($type === 'customer') {
            $person = Customer::findOrFail($id);
            $personLabel = __('messages.customer');
        } elseif ($type === 'supplier') {
            $person = Supplier::findOrFail($id);
            $personLabel = __('messages.supplier');
        } else {
            abort(404);
        }
        $personType = $type;

        $orderIds = Order::where('person_type', $personType)->where('person_id', $id)->pluck('id');
        $purchaseIds = Purchase::where('person_type', $personType)->where('person_id', $id)->pluck('id');

        $paymentsFromOrdersAFN = Payment::whereIn('order_id', $orderIds)->where('currency', 'AFN')->sum('amount');
        $paymentsFromOrdersUSD = Payment::whereIn('order_id', $orderIds)->where('currency', 'USD')->sum('amount');
        $paymentsFromPurchasesAFN = PurchasePayment::whereIn('purchase_id', $purchaseIds)->where('currency', 'AFN')->sum('amount');
        $paymentsFromPurchasesUSD = PurchasePayment::whereIn('purchase_id', $purchaseIds)->where('currency', 'USD')->sum('amount');

        $ledgerType = $type === 'customer' ? 'payment_received' : 'payment_made';
        $ledgerPaymentsAFN = PartyPayment::where('person_type', $personType)->where('person_id', $id)
            ->where('currency', 'AFN')->where('type', $ledgerType)->sum('amount');
        $ledgerPaymentsUSD = PartyPayment::where('person_type', $personType)->where('person_id', $id)
            ->where('currency', 'USD')->where('type', $ledgerType)->sum('amount');

        $paidAFN = $paymentsFromOrdersAFN + $paymentsFromPurchasesAFN + $ledgerPaymentsAFN;
        $paidUSD = $paymentsFromOrdersUSD + $paymentsFromPurchasesUSD + $ledgerPaymentsUSD;

        $totalAFN = Order::whereIn('id', $orderIds)->where('currency', 'AFN')->sum('total_amount')
                    + Purchase::whereIn('id', $purchaseIds)->where('currency', 'AFN')->sum('total_amount');
        $totalUSD = Order::whereIn('id', $orderIds)->where('currency', 'USD')->sum('total_amount')
                    + Purchase::whereIn('id', $purchaseIds)->where('currency', 'USD')->sum('total_amount');

        $entries = PartyPayment::where('person_type', $personType)
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

        $person = $type === 'customer'
            ? Customer::findOrFail($id)
            : Supplier::findOrFail($id);

        $entryType = $type === 'customer' ? 'payment_received' : 'payment_made';

        PartyPayment::create([
            'person_type' => $type === 'customer' ? 'customer' : 'supplier',
            'person_id' => $id,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'type' => $entryType,
            'notes' => $validated['notes'],
        ]);

        $personLabel = $type === 'customer' ? __('messages.customer') : __('messages.supplier');
        return redirect()->route('ledger.show', [$type, $id])
            ->with('success', __('messages.payment_created_for') . ' ' . $personLabel . '!');
    }

    public function downloadPdf($type, $id)
    {
        if ($type === 'customer') {
            $person = Customer::findOrFail($id);
            $personType = 'customer';
            $personLabel = __('messages.customer');
            $typeLabel = __('messages.customer_ledger');
        } elseif ($type === 'supplier') {
            $person = Supplier::findOrFail($id);
            $personType = 'supplier';
            $personLabel = __('messages.supplier');
            $typeLabel = __('messages.supplier_ledger');
        } else {
            abort(404);
        }

        $orders = Order::with('payments')
            ->where('person_type', $personType)
            ->where('person_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $purchases = Purchase::with('purchasePayments')
            ->where('person_type', $personType)
            ->where('person_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $ledgerEntries = PartyPayment::where('person_type', $personType)
            ->where('person_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $totalOrdersAFN = $orders->where('currency', 'AFN')->sum('total_amount')
                        + $purchases->where('currency', 'AFN')->sum('total_amount');
        $totalOrdersUSD = $orders->where('currency', 'USD')->sum('total_amount')
                        + $purchases->where('currency', 'USD')->sum('total_amount');

        $transactions = [];

        foreach ($orders as $order) {
            $transactions[] = [
                'date' => $order->created_at,
                'description' => __('messages.order') . ' #' . $order->id,
                'type' => 'order',
                'ref' => 'ORD-' . $order->id,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'balance' => 0,
                'is_positive' => true,
            ];

            foreach ($order->payments as $payment) {
                $transactions[] = [
                    'date' => $payment->created_at,
                    'description' => __('messages.order_of') . ' #' . $order->id,
                    'type' => 'payment',
                    'ref' => 'PAY-' . $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'balance' => 0,
                    'is_positive' => false,
                ];
            }
        }

        foreach ($purchases as $purchase) {
            $transactions[] = [
                'date' => $purchase->created_at,
                'description' => __('messages.purchase') . ' #' . $purchase->id,
                'type' => 'purchase',
                'ref' => 'PUR-' . $purchase->id,
                'amount' => $purchase->total_amount,
                'currency' => $purchase->currency,
                'balance' => 0,
                'is_positive' => true,
            ];

            foreach ($purchase->purchasePayments as $purchasePayment) {
                $transactions[] = [
                    'date' => $purchasePayment->created_at,
                    'description' => __('messages.purchase_of') . ' #' . $purchase->id,
                    'type' => 'purchase_payment',
                    'ref' => 'PPAY-' . $purchasePayment->id,
                    'amount' => $purchasePayment->amount,
                    'currency' => $purchasePayment->currency,
                    'balance' => 0,
                    'is_positive' => false,
                ];
            }
        }

        $ledgerSign = $type === 'customer' ? 'payment_made' : 'payment_received';
        foreach ($ledgerEntries as $entry) {
            $description = $entry->notes ?? ($entry->type === 'payment_received' ? __('messages.receipt_amount') : __('messages.paid_short'));
            $transactions[] = [
                'date' => $entry->created_at,
                'description' => $description,
                'type' => 'ledger',
                'ref' => 'LE-' . $entry->id,
                'amount' => $entry->amount,
                'currency' => $entry->currency,
                'balance' => 0,
                'is_positive' => $entry->type === $ledgerSign,
            ];
        }

        usort($transactions, function ($a, $b) {
            return $a['date']->lte($b['date']) ? -1 : 1;
        });

        $balances = ['AFN' => 0, 'USD' => 0];
        foreach ($transactions as &$tx) {
            $curr = $tx['currency'];
            $balances[$curr] += $tx['is_positive'] ? $tx['amount'] : -$tx['amount'];
            $tx['balance'] = $balances[$curr];
        }
        unset($tx);

        $openingBalanceAFN = 0;
        $openingBalanceUSD = 0;
        $closingBalanceAFN = $balances['AFN'];
        $closingBalanceUSD = $balances['USD'];

        $totalPaidAFN = max(0, -$balances['AFN']);
        $totalPaidUSD = max(0, -$balances['USD']);
        $remainingAFN = max(0, $balances['AFN']);
        $remainingUSD = max(0, $balances['USD']);

        $totalDrAFN = 0;
        $totalCrAFN = 0;
        $totalDrUSD = 0;
        $totalCrUSD = 0;
        foreach ($transactions as $tx) {
            if ($tx['currency'] === 'AFN') {
                if ($tx['is_positive']) {
                    $totalDrAFN += $tx['amount'];
                } else {
                    $totalCrAFN += $tx['amount'];
                }
            } else {
                if ($tx['is_positive']) {
                    $totalDrUSD += $tx['amount'];
                } else {
                    $totalCrUSD += $tx['amount'];
                }
            }
        }

        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
            'email' => Setting::get('company_email', ''),
        ];

        return view('ledger.pdf', compact(
            'company', 'person', 'personType', 'personLabel', 'typeLabel',
            'transactions', 'openingBalanceAFN', 'openingBalanceUSD',
            'closingBalanceAFN', 'closingBalanceUSD',
            'totalOrdersAFN', 'totalOrdersUSD',
            'totalPaidAFN', 'totalPaidUSD',
            'remainingAFN', 'remainingUSD',
            'totalDrAFN', 'totalCrAFN', 'totalDrUSD', 'totalCrUSD'
        ));
    }
}
