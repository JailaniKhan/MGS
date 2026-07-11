<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\PartyPayment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PassbookController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = collect();

        // Sales (Credit In)
        $orders = Order::with(['customer', 'supplier'])->get();
        foreach ($orders as $order) {
            $query->push([
                'date' => $order->created_at,
                'type' => 'credit_in',
                'description' => __('messages.sale_to') . ' ' . ($order->party?->name ?? __('messages.unknown')),
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'reference' => 'ORD-' . $order->id,
                'icon' => 'sale',
            ]);
        }

        // Customer Payments (Cash In)
        $payments = Payment::with('order.customer', 'order.supplier')->get();
        foreach ($payments as $payment) {
            $query->push([
                'date' => $payment->created_at,
                'type' => 'cash_in',
                'description' => __('messages.payment_from') . ' ' . ($payment->order->party?->name ?? __('messages.unknown')),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => 'PAY-' . $payment->id,
                'icon' => 'cash_in',
            ]);
        }

        // Purchases (Credit Out)
        $purchases = Purchase::with(['customer', 'supplier'])->get();
        foreach ($purchases as $purchase) {
            $query->push([
                'date' => $purchase->created_at,
                'type' => 'credit_out',
                'description' => __('messages.purchase_from') . ' ' . ($purchase->party?->name ?? __('messages.unknown')),
                'amount' => $purchase->total_amount,
                'currency' => $purchase->currency,
                'reference' => 'PUR-' . $purchase->id,
                'icon' => 'purchase',
            ]);
        }

        // Supplier Payments (Cash Out)
        $purchasePayments = PurchasePayment::with('purchase.customer', 'purchase.supplier')->get();
        foreach ($purchasePayments as $pp) {
            $query->push([
                'date' => $pp->created_at,
                'type' => 'cash_out',
                'description' => __('messages.payment_to') . ' ' . ($pp->purchase->party?->name ?? __('messages.unknown')),
                'amount' => $pp->amount,
                'currency' => $pp->currency,
                'reference' => 'PPAY-' . $pp->id,
                'icon' => 'cash_out',
            ]);
        }

        // Expenses
        $expenses = Expense::all();
        foreach ($expenses as $expense) {
            $query->push([
                'date' => $expense->expense_date,
                'type' => 'expense',
                'description' => $expense->category . ($expense->notes ? ' - ' . $expense->notes : ''),
                'amount' => $expense->amount,
                'currency' => $expense->currency,
                'reference' => 'EXP-' . $expense->id,
                'icon' => 'expense',
            ]);
        }

        // Filter by type
        if ($filter !== 'all') {
            $query = $query->filter(fn($item) => $item['type'] === $filter);
        }

        // Filter by date range
        if ($dateFrom) {
            $query = $query->filter(fn($item) => $item['date']->gte(Carbon::parse($dateFrom)));
        }
        if ($dateTo) {
            $query = $query->filter(fn($item) => $item['date']->lte(Carbon::parse($dateTo)->endOfDay()));
        }

        // Sort by date descending
        $transactions = $query->sortByDesc('date')->values();

        // Calculate totals
        $totalCreditIn = $query->filter(fn($i) => in_array($i['type'], ['credit_in', 'cash_in']) && $i['currency'] === 'AFN')->sum('amount');
        $totalCreditInUSD = $query->filter(fn($i) => in_array($i['type'], ['credit_in', 'cash_in']) && $i['currency'] === 'USD')->sum('amount');
        $totalCreditOut = $query->filter(fn($i) => in_array($i['type'], ['credit_out', 'cash_out', 'expense']) && $i['currency'] === 'AFN')->sum('amount');
        $totalCreditOutUSD = $query->filter(fn($i) => in_array($i['type'], ['credit_out', 'cash_out', 'expense']) && $i['currency'] === 'USD')->sum('amount');

        return view('passbook.index', compact('transactions', 'filter', 'dateFrom', 'dateTo', 'totalCreditIn', 'totalCreditInUSD', 'totalCreditOut', 'totalCreditOutUSD'));
    }
}
