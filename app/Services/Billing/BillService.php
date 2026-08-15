<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Reminder;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\WhatsApp\OpenWaService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BillService
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private OpenWaService $openWaService,
    ) {}

    /**
     * Build a plain-text order invoice (mirrors orders/print.blade.php).
     *
     * @return array{phone: string, message: string, amount: float, currency: string}
     */
    public function orderBill(Order $order): array
    {
        $order->loadMissing('customer', 'supplier', 'orderItems.product.unit', 'payments');
        $party = $order->party;
        $currency = $order->currency_symbol;
        $invoiceNumber = Setting::get('invoice_prefix', 'INV-') . $order->id;

        $lines = $this->companyHeader();
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.invoice') . ' #' . $invoiceNumber;
        $lines[] = __('messages.date') . ': ' . $order->created_at->format('Y/m/d H:i');
        $lines[] = __('messages.status') . ': ' . $this->statusLabel($order->display_status);
        $lines[] = str_repeat('-', 30);
        $lines = array_merge($lines, $this->partyLines($party));
        $lines[] = str_repeat('=', 30);

        foreach ($order->orderItems as $item) {
            $productName = $item->product?->name ?? '#' . $item->product_id;
            $unit = $item->product?->unit ? ($item->product->unit->short_name ?? $item->product->unit->name) : '';
            $lines[] = $productName;
            $lines[] = '  ' . $item->quantity . ($unit ? ' ' . $unit : '')
                . ' x ' . number_format((float) $item->unit_price)
                . ' = ' . number_format((float) $item->subtotal) . ' ' . $currency;
            if ($item->lot_number) {
                $lines[] = '  ' . __('messages.lot_number') . ': ' . $item->lot_number;
            }
        }

        $lines[] = str_repeat('-', 30);

        $lines[] = __('messages.total_amount') . ': ' . number_format((float) $order->total_amount) . ' ' . $currency;
        $lines[] = __('messages.paid') . ': ' . number_format($order->paid_amount) . ' ' . $currency;
        $lines[] = $order->is_fully_paid
            ? __('messages.fully_paid')
            : __('messages.remaining') . ': ' . number_format($order->remaining_amount) . ' ' . $currency;
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.business_mgmt_system') . ' (MGS)';

        return [
            'phone' => $party?->phone ?? '',
            'message' => implode("\n", $lines),
            'amount' => (float) $order->total_amount,
            'currency' => $order->currency,
        ];
    }

    /**
     * Build a plain-text purchase bill (mirrors the new purchases/print.blade.php).
     *
     * @return array{phone: string, message: string, amount: float, currency: string}
     */
    public function purchaseBill(Purchase $purchase): array
    {
        $purchase->loadMissing('customer', 'supplier', 'purchaseItems.product.unit', 'purchasePayments');
        $party = $purchase->party;
        $currency = $purchase->currency_symbol;
        $billNumber = Setting::get('purchase_prefix', 'PUR-') . $purchase->id;

        $lines = $this->companyHeader();
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.purchase') . ' #' . $billNumber;
        $lines[] = __('messages.date') . ': ' . $purchase->created_at->format('Y/m/d H:i');
        $lines[] = __('messages.status') . ': ' . $this->statusLabel($purchase->display_status);
        $lines[] = str_repeat('-', 30);
        $lines = array_merge($lines, $this->partyLines($party));
        $lines[] = str_repeat('=', 30);

        foreach ($purchase->purchaseItems as $item) {
            $productName = $item->product?->name ?? '#' . $item->product_id;
            $unit = $item->product?->unit ? ($item->product->unit->short_name ?? $item->product->unit->name) : '';
            $lines[] = $productName;
            $lines[] = '  ' . $item->quantity . ($unit ? ' ' . $unit : '')
                . ' x ' . number_format((float) $item->unit_price)
                . ' = ' . number_format((float) $item->subtotal) . ' ' . $currency;
            if ($item->lot_number) {
                $lines[] = '  ' . __('messages.lot_number') . ': ' . $item->lot_number;
            }
        }

        $lines[] = str_repeat('-', 30);

        $lines[] = __('messages.total_amount') . ': ' . number_format((float) $purchase->total_amount) . ' ' . $currency;
        $lines[] = __('messages.paid') . ': ' . number_format($purchase->paid_amount) . ' ' . $currency;
        $lines[] = $purchase->is_fully_paid
            ? __('messages.fully_paid')
            : __('messages.remaining') . ': ' . number_format($purchase->remaining_amount) . ' ' . $currency;
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.business_mgmt_system') . ' (MGS)';

        return [
            'phone' => $party?->phone ?? '',
            'message' => implode("\n", $lines),
            'amount' => (float) $purchase->total_amount,
            'currency' => $purchase->currency,
        ];
    }

    /**
     * Build a plain-text cashbook statement for one customer/supplier
     * (mirrors cashbook/person.blade.php).
     *
     * @param array<string, array{in: float, out: float, net: float}> $totals
     * @param \Illuminate\Support\Collection<int, object> $transactions
     * @return array{phone: string, message: string, amount: float, currency: string}
     */
    public function personStatement(string $type, Customer|Supplier $person, array $totals, $transactions): array
    {
        $lines = $this->companyHeader();
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.statement');
        $lines[] = __('messages.party') . ': ' . $person->name;
        $lines[] = __('messages.date') . ': ' . now()->format('Y/m/d H:i');
        if ($person->phone) {
            $lines[] = __('messages.phone') . ': ' . $person->phone;
        }
        $lines[] = str_repeat('=', 30);

        foreach (['AFN', 'USD'] as $currency) {
            if (! isset($totals[$currency]) || ((float) $totals[$currency]['in'] == 0 && (float) $totals[$currency]['out'] == 0)) {
                continue;
            }

            $total = $totals[$currency];
            $lines[] = __('messages.cashbook_in_total') . ': ' . number_format((float) $total['in'], 2) . ' ' . $currency;
            $lines[] = __('messages.cashbook_out_total') . ': ' . number_format((float) $total['out'], 2) . ' ' . $currency;
            $netLabel = $total['net'] >= 0 ? '+' : '-';
            $lines[] = __('messages.net_balance') . ': ' . $netLabel . number_format(abs((float) $total['net']), 2) . ' ' . $currency;
            $lines[] = str_repeat('-', 30);
        }

        foreach ($transactions as $tx) {
            $sign = $tx->direction === 'in' ? '+' : '-';
            $lines[] = $tx->date->format('d M Y')
                . ' | ' . $tx->label
                . ' | ' . $sign . number_format((float) $tx->amount, 2) . ' ' . $tx->currency;
            if (! empty($tx->notes)) {
                $lines[] = '  (' . $tx->notes . ')';
            }
        }

        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.business_mgmt_system') . ' (MGS)';

        return [
            'phone' => $person->phone ?? '',
            'message' => implode("\n", $lines),
            'amount' => null,
            'currency' => '',
        ];
    }

    /**
     * Record the bill as a WhatsApp reminder and deliver it through the gateway.
     * The reminder is attached to the party (Customer/Supplier) so the bill
     * shows up in the WhatsApp Chats thread of that person.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function send(?Model $party, string $phone, string $message, ?float $amount, string $currency): array
    {
        if (empty(trim($phone))) {
            return ['ok' => false, 'error' => __('messages.no_phone_to_send')];
        }

        $reminder = Reminder::create([
            'user_id' => auth()->id(),
            'remindable_type' => $party ? get_class($party) : null,
            'remindable_id' => $party?->id,
            'amount' => $amount,
            'currency' => $currency ?: 'AFN',
            'channel' => 'whatsapp',
            'message' => $message,
            'status' => 'pending',
        ]);

        try {
            if ($this->openWaService && $this->openWaService->isConfigured()) {
                $sessionState = $this->openWaService->sessionStatus()['status'] ?? null;

                if ($sessionState !== 'ready' && $sessionState !== 'connected') {
                    $reminder->update([
                        'status' => 'failed',
                        'error_message' => "WhatsApp session not connected (state: {$sessionState}). Link the device in Settings → WhatsApp Gateway.",
                    ]);

                    return ['ok' => false, 'error' => __('messages.bill_send_failed')];
                }
            }

            $sent = $this->whatsAppService?->send($phone, $message) ?? false;

            $reminder->update([
                'status' => $sent ? 'sent' : 'failed',
                'sent_at' => $sent ? now() : null,
                'error_message' => $sent ? null : 'WhatsApp gateway rejected the message.',
            ]);

            return $sent
                ? ['ok' => true, 'error' => null]
                : ['ok' => false, 'error' => __('messages.bill_send_failed')];
        } catch (\Exception $e) {
            Log::error('WhatsApp bill send failed', [
                'reminder_id' => $reminder->id,
                'error' => $e->getMessage(),
            ]);

            $reminder->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => __('messages.bill_send_failed')];
        }
    }

    private function companyHeader(): array
    {
        $company = [
            'name' => Setting::get('company_name', 'My Business'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
            'email' => Setting::get('company_email', ''),
        ];

        $lines = [$company['name']];

        if ($company['address']) {
            $lines[] = $company['address'];
        }

        if ($company['phone']) {
            $lines[] = __('messages.phone') . ': ' . $company['phone'];
        }

        if ($company['email']) {
            $lines[] = __('messages.email') . ': ' . $company['email'];
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function partyLines(?Model $party): array
    {
        if (! $party) {
            return [__('messages.unknown')];
        }

        $lines = [$party->name];

        if ($party->phone) {
            $lines[] = __('messages.phone') . ': ' . $party->phone;
        }

        if ($party->address) {
            $lines[] = __('messages.address') . ': ' . $party->address;
        }

        return $lines;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'paid' => __('messages.paid'),
            'completed' => __('messages.completed'),
            'processing' => __('messages.processing'),
            'cancelled' => __('messages.cancelled'),
            default => __('messages.pending'),
        };
    }
}
