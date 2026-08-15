<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AddsListBalances;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Accounting\BalanceService;
use App\Services\Accounting\TransactionService;
use App\Services\Billing\BillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CashbookController extends Controller
{
    use AddsListBalances;

    public function index(BalanceService $balanceService)
    {
        $userId = Auth::id();
        $journals = JournalEntry::query()
            ->whereIn('source', ['cashbook_in', 'cashbook_out'])
            ->with(['reference', 'ledgerEntries'])
            ->latest('transaction_date')
            ->get();

        // `journal_entries` has no `type`/`total_amount` columns; the cashbook
        // amount and direction live in `ledger_entries`. Derive them here so the
        // view can render the correct sign and amount for every entry.
        $journals->transform(function (JournalEntry $journal) {
            $cashLine = $journal->ledgerEntries->first();

            $journal->setAttribute('type', $journal->source === 'cashbook_in' ? 'in' : 'out');
            $journal->setAttribute('total_amount', $cashLine ? (float) $cashLine->amount : 0);

            return $journal;
        });

        // Group the cashbook entries by the person they reference so the page
        // can show one clickable row per customer/supplier.
        $people = [];

        foreach ($journals as $journal) {
            if (! $journal->reference_type || ! $journal->reference) {
                continue;
            }

            $key = $journal->reference_type.'-'.$journal->reference->id;

            if (! isset($people[$key])) {
                $people[$key] = [
                    'id' => $journal->reference->id,
                    'name' => $journal->reference->name,
                    'type' => $journal->reference_type,
                    'in' => ['AFN' => 0.0, 'USD' => 0.0],
                    'out' => ['AFN' => 0.0, 'USD' => 0.0],
                    'count' => 0,
                ];
            }

            $people[$key]['count']++;

            if ($journal->type === 'in') {
                $people[$key]['in'][$journal->currency] += $journal->total_amount;
            } else {
                $people[$key]['out'][$journal->currency] += $journal->total_amount;
            }
        }

        $people = $this->paginateCollection(
            collect(array_values($people))->sortBy('name')->values()
        );

        $cashAFN = $balanceService->cashBalance($userId, 'AFN');
        $cashUSD = $balanceService->cashBalance($userId, 'USD');

        return view('cashbook.index', [
            'people' => $people,
            'cashAFN' => $cashAFN,
            'cashUSD' => $cashUSD,
            'cashUnbalanced' => bccomp($cashAFN, '0', 2) === -1 || bccomp($cashUSD, '0', 2) === -1,
        ]);
    }

    public function person($type, $id)
    {
        return view('cashbook.person', $this->personData($type, $id));
    }

    private function personData($type, $id)
    {
        abort_if(! in_array($type, ['customer', 'supplier'], true), 404);

        if ($type === 'customer') {
            $person = Customer::findOrFail($id);
        } else {
            $person = Supplier::findOrFail($id);
        }

        $cashbook = JournalEntry::query()
            ->whereIn('source', ['cashbook_in', 'cashbook_out'])
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->with(['ledgerEntries'])
            ->latest('transaction_date')
            ->get()
            ->transform(function (JournalEntry $journal) {
                $cashLine = $journal->ledgerEntries->first();

                $journal->setAttribute('type', $journal->source === 'cashbook_in' ? 'in' : 'out');
                $journal->setAttribute('total_amount', $cashLine ? (float) $cashLine->amount : 0);

                return $journal;
            });

        // Orders/purchases are linked to a person via person_type / person_id
        // (a customer sells, a supplier can both buy and be sold to). Load them
        // by that linkage rather than the legacy customer_id relation. Cancelled
        // documents owe nothing, so they must not count as outstanding.
        $orders = Order::where('person_type', $type)
            ->where('person_id', $id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->get();

        $this->attachOrderListBalances($orders);
        $orders = $orders->filter(fn ($order) => bccomp($order->remaining, '0', 2) > 0);

        $purchases = Purchase::where('person_type', $type)
            ->where('person_id', $id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->get();

        $this->attachPurchaseListBalances($purchases);
        $purchases = $purchases->filter(fn ($purchase) => bccomp($purchase->remaining, '0', 2) > 0);

        // Build one combined, chronologically ordered list of everything tied to
        // this person. Cashbook entries drive the In/Out/Net totals; orders and
        // purchases are shown as context only.
        $transactions = collect();

        foreach ($cashbook as $entry) {
            $transactions->push((object) [
                'kind' => 'cashbook',
                'direction' => $entry->type,
                'label' => $entry->description,
                'date' => $entry->transaction_date,
                'amount' => $entry->total_amount,
                'currency' => $entry->currency,
                'notes' => $entry->notes ?? '',
            ]);
        }

        foreach ($orders as $order) {
            $transactions->push((object) [
                'kind' => 'order',
                'id' => $order->id,
                'direction' => 'in',
                'label' => __('messages.order').' #'.$order->id,
                'date' => $order->created_at,
                'amount' => (float) $order->remaining,
                'currency' => $order->currency,
                'notes' => ucfirst($order->list_status ?? ''),
            ]);
        }

        foreach ($purchases as $purchase) {
            $transactions->push((object) [
                'kind' => 'purchase',
                'id' => $purchase->id,
                'direction' => 'out',
                'label' => __('messages.purchase').' #'.$purchase->id,
                'date' => $purchase->created_at,
                'amount' => (float) $purchase->remaining,
                'currency' => $purchase->currency,
                'notes' => ucfirst($purchase->list_status ?? ''),
            ]);
        }

        $transactions = $transactions->sortByDesc('date')->values();

        $totals = [];
        $orderTotals = [];
        $purchaseTotals = [];
        foreach (['AFN', 'USD'] as $currency) {
            $in = $cashbook->where('currency', $currency)->where('type', 'in')->sum('total_amount');
            $out = $cashbook->where('currency', $currency)->where('type', 'out')->sum('total_amount');
            $totals[$currency] = [
                'in' => (float) $in,
                'out' => (float) $out,
                'net' => (float) $in - (float) $out,
            ];
            $orderTotals[$currency] = (float) $orders->where('currency', $currency)->sum('remaining');
            $purchaseTotals[$currency] = (float) $purchases->where('currency', $currency)->sum('remaining');
        }

        return [
            'person' => $person,
            'personType' => $type,
            'transactions' => $transactions,
            'totals' => $totals,
            'orderTotals' => $orderTotals,
            'purchaseTotals' => $purchaseTotals,
        ];
    }

    public function sendStatement($type, $id, BillService $bills)
    {
        $data = $this->personData($type, $id);

        $bill = $bills->personStatement(
            $data['personType'],
            $data['person'],
            $data['totals'],
            $data['transactions'],
        );

        $result = $bills->send(
            $data['person'],
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

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

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

        return view('cashbook.create', compact('personOptions'));
    }

    public function store(Request $request, TransactionService $transactionService)
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'entry_date' => 'required|date',
            'person' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $meta = [
            'description' => $validated['type'] === 'in' ? __('messages.cash_income') : __('messages.cash_expense'),
            'transaction_date' => $validated['entry_date'],
            'notes' => $validated['notes'] ?? null,
        ];

        if (! empty($validated['person'])) {
            [$personType, $personId] = array_pad(explode(':', $validated['person'], 2), 2, null);

            $personExists = in_array($personType, ['customer', 'supplier'], true)
                && ctype_digit((string) $personId)
                && ($personType === 'customer'
                    ? Customer::whereKey((int) $personId)->exists()
                    : Supplier::whereKey((int) $personId)->exists());

            if (! $personExists) {
                throw ValidationException::withMessages([
                    'person' => __('messages.select_valid_person'),
                ]);
            }

            $meta['reference_type'] = $personType;
            $meta['reference_id'] = (int) $personId;
        }

        $transactionService->postCashbookEntry(
            Auth::user(),
            $validated['type'],
            number_format((float) $validated['amount'], 2, '.', ''),
            $validated['currency'],
            $meta
        );

        return redirect()->route('cashbook.index')->with('success', __('messages.cashbook_recorded'));
    }
}
