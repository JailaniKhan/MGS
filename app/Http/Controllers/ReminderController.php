<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Reminder\ReminderService;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function __construct(private ReminderService $reminderService) {}

    public function sendCustomerReminder(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'channel' => 'required|in:sms,whatsapp',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|in:AFN,USD',
        ]);

        $currency = $validated['currency'] ?? 'AFN';
        $totalOrders = $customer->orders()->where('currency', $currency)->sum('total_amount');

        $paidPayments = $customer->orders()
            ->where('currency', $currency)
            ->get()
            ->sum(function ($order) {
                return $order->payments()->where('currency', $order->currency)->sum('amount');
            });

        $ledgerPayments = \App\Models\PartyPayment::where('person_type', 'customer')
            ->where('person_id', $customer->id)
            ->where('currency', $currency)
            ->where('type', 'payment_received')
            ->sum('amount');

        $pendingAmount = max(0, $totalOrders - $paidPayments - $ledgerPayments);

        $amount = $validated['amount'] ?? $pendingAmount;

        if (!$customer->phone) {
            return back()->with('error', __('messages.customer_no_phone'));
        }

        if ($amount <= 0) {
            return back()->with('error', __('messages.no_pending_amount'));
        }

        $this->reminderService->sendReminder(
            remindableType: 'customer',
            remindableId: $customer->id,
            name: $customer->name,
            phone: $customer->phone,
            amount: (string) $amount,
            currency: $currency,
            channel: $validated['channel'],
            dueDate: now()->addDays(7)->format('Y-m-d'),
        );

        return back()->with('success', __('messages.reminder_sent'));
    }

    public function sendSupplierReminder(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'channel' => 'required|in:sms,whatsapp',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|in:AFN,USD',
        ]);

        $currency = $validated['currency'] ?? 'AFN';
        $totalPurchases = $supplier->purchases()->where('currency', $currency)->sum('total_amount');

        $paidPayments = $supplier->purchases()
            ->where('currency', $currency)
            ->get()
            ->sum(function ($purchase) {
                return $purchase->purchasePayments()->where('currency', $purchase->currency)->sum('amount');
            });

        $ledgerPayments = \App\Models\PartyPayment::where('person_type', 'supplier')
            ->where('person_id', $supplier->id)
            ->where('currency', $currency)
            ->where('type', 'payment_made')
            ->sum('amount');

        $pendingAmount = max(0, $totalPurchases - $paidPayments - $ledgerPayments);

        $amount = $validated['amount'] ?? $pendingAmount;

        if (!$supplier->phone) {
            return back()->with('error', __('messages.supplier_no_phone'));
        }

        if ($amount <= 0) {
            return back()->with('error', __('messages.no_pending_amount'));
        }

        $this->reminderService->sendReminder(
            remindableType: 'supplier',
            remindableId: $supplier->id,
            name: $supplier->name,
            phone: $supplier->phone,
            amount: (string) $amount,
            currency: $currency,
            channel: $validated['channel'],
        );

        return back()->with('success', __('messages.reminder_sent'));
    }

    public function history()
    {
        $reminders = \App\Models\Reminder::with('remindable')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('reminders.index', compact('reminders'));
    }
}
