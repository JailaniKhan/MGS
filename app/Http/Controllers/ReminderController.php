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
        $totalOrders = $customer->orders()->where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');

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
            return $request->expectsJson()
                ? response()->json(['error' => __('messages.customer_no_phone')], 422)
                : back()->with('error', __('messages.customer_no_phone'));
        }

        if ($amount <= 0) {
            return $request->expectsJson()
                ? response()->json(['error' => __('messages.no_pending_amount')], 422)
                : back()->with('error', __('messages.no_pending_amount'));
        }

        $reminder = $this->reminderService->sendReminder(
            remindableType: 'customer',
            remindableId: $customer->id,
            name: $customer->name,
            phone: $customer->phone,
            amount: (string) $amount,
            currency: $currency,
            channel: $validated['channel'],
            dueDate: now()->addDays(7)->format('Y-m-d'),
            userId: $request->user()?->id,
        );

        // SMS: return the message so the frontend can open the native SMS app
        if ($validated['channel'] === 'sms') {
            return response()->json([
                'phone' => $customer->phone,
                'message' => $reminder->message,
                'status' => 'drafted',
            ]);
        }

        if ($reminder->status === 'sent') {
            return back()->with('success', __('messages.reminder_sent'));
        }

        return back()->with('error', __('messages.reminder_failed'));
    }

    public function sendSupplierReminder(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'channel' => 'required|in:sms,whatsapp',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|in:AFN,USD',
        ]);

        $currency = $validated['currency'] ?? 'AFN';
        $totalPurchases = $supplier->purchases()->where('currency', $currency)->where('status', '!=', 'cancelled')->sum('total_amount');

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
            return $request->expectsJson()
                ? response()->json(['error' => __('messages.supplier_no_phone')], 422)
                : back()->with('error', __('messages.supplier_no_phone'));
        }

        if ($amount <= 0) {
            return $request->expectsJson()
                ? response()->json(['error' => __('messages.no_pending_amount')], 422)
                : back()->with('error', __('messages.no_pending_amount'));
        }

        $reminder = $this->reminderService->sendReminder(
            remindableType: 'supplier',
            remindableId: $supplier->id,
            name: $supplier->name,
            phone: $supplier->phone,
            amount: (string) $amount,
            currency: $currency,
            channel: $validated['channel'],
            dueDate: now()->addDays(7)->format('Y-m-d'),
            userId: $request->user()?->id,
        );

        // SMS: return the message so the frontend can open the native SMS app
        if ($validated['channel'] === 'sms') {
            return response()->json([
                'phone' => $supplier->phone,
                'message' => $reminder->message,
                'status' => 'drafted',
            ]);
        }

        if ($reminder->status === 'sent') {
            return back()->with('success', __('messages.reminder_sent'));
        }

        return back()->with('error', __('messages.reminder_failed'));
    }

    public function history()
    {
        $reminders = \App\Models\Reminder::with('remindable')
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('reminders.index', compact('reminders'));
    }
}
