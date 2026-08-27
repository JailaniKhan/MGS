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
        // Canonical pending amount (same rule as orders/wallet/ledger pages):
        // only non-cancelled docs, same-currency payments, returns reduce.
        $pendingAmount = app(\App\Services\Billing\PartyBalanceService::class)
            ->pendingAmount('customer', $customer->id, $currency);

        $amount = $validated['amount'] ?? (float) $pendingAmount;

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
        // Canonical pending amount (same rule as orders/wallet/ledger pages).
        $pendingAmount = app(\App\Services\Billing\PartyBalanceService::class)
            ->pendingAmount('supplier', $supplier->id, $currency);

        $amount = $validated['amount'] ?? (float) $pendingAmount;

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
