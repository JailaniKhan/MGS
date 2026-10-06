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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $invoiceNumber = Setting::get('invoice_prefix', 'INV-').$order->id;

        $lines = $this->companyHeader();
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.invoice').' #'.$invoiceNumber;
        $lines[] = __('messages.date').': '.$order->created_at->format('Y/m/d H:i');
        $lines[] = __('messages.status').': '.$this->statusLabel($order->display_status);
        $lines[] = str_repeat('-', 30);
        $lines = array_merge($lines, $this->partyLines($party));
        $lines[] = str_repeat('=', 30);

        foreach ($order->orderItems as $item) {
            $productName = $item->product?->name ?? '#'.$item->product_id;
            $unit = $item->product?->unit ? ($item->product->unit->short_name ?? $item->product->unit->name) : '';
            $lines[] = $productName;
            $lines[] = '  '.$this->isolated(fn () => $item->quantity.($unit ? ' '.$unit : '')
                .' x '.money_format($item->unit_price)
                .' = '.money_format($item->subtotal).' '.$currency);
            if ($item->lot_number) {
                $lines[] = '  '.__('messages.lot_number').': '.$item->lot_number;
            }
        }

        $lines[] = str_repeat('-', 30);

        $lines[] = __('messages.total_amount').': '.$this->isolated(fn () => money_format($order->total_amount).' '.$currency);
        $lines[] = __('messages.paid').': '.$this->isolated(fn () => money_format($order->paid_amount).' '.$currency);
        $lines[] = $order->is_fully_paid
            ? __('messages.fully_paid')
            : __('messages.remaining').': '.$this->isolated(fn () => money_format($order->remaining_amount).' '.$currency);
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.business_mgmt_system').' (MGS)';

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
        $billNumber = Setting::get('purchase_prefix', 'PUR-').$purchase->id;

        $lines = $this->companyHeader();
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.purchase').' #'.$billNumber;
        $lines[] = __('messages.date').': '.$purchase->created_at->format('Y/m/d H:i');
        $lines[] = __('messages.status').': '.$this->statusLabel($purchase->display_status);
        $lines[] = str_repeat('-', 30);
        $lines = array_merge($lines, $this->partyLines($party));
        $lines[] = str_repeat('=', 30);

        foreach ($purchase->purchaseItems as $item) {
            $productName = $item->product?->name ?? '#'.$item->product_id;
            $unit = $item->product?->unit ? ($item->product->unit->short_name ?? $item->product->unit->name) : '';
            $lines[] = $productName;
            $lines[] = '  '.$this->isolated(fn () => $item->quantity.($unit ? ' '.$unit : '')
                .' x '.money_format($item->unit_price)
                .' = '.money_format($item->subtotal).' '.$currency);
            if ($item->lot_number) {
                $lines[] = '  '.__('messages.lot_number').': '.$item->lot_number;
            }
        }

        $lines[] = str_repeat('-', 30);

        $lines[] = __('messages.total_amount').': '.$this->isolated(fn () => money_format($purchase->total_amount).' '.$currency);
        $lines[] = __('messages.paid').': '.$this->isolated(fn () => money_format($purchase->paid_amount).' '.$currency);
        $lines[] = $purchase->is_fully_paid
            ? __('messages.fully_paid')
            : __('messages.remaining').': '.$this->isolated(fn () => money_format($purchase->remaining_amount).' '.$currency);
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.business_mgmt_system').' (MGS)';

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
     * @param  array<string, array{in: float, out: float, net: float}>  $totals
     * @param  Collection<int, object>  $transactions
     * @return array{phone: string, message: string, amount: float, currency: string}
     */
    public function personStatement(string $type, Customer|Supplier $person, array $totals, $transactions): array
    {
        $lines = $this->companyHeader();
        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.statement');
        $lines[] = __('messages.party').': '.$person->name;
        $lines[] = __('messages.date').': '.now()->format('Y/m/d H:i');
        if ($person->phone) {
            $lines[] = __('messages.phone').': '.$person->phone;
        }
        $lines[] = str_repeat('=', 30);

        foreach (['AFN', 'USD'] as $currency) {
            if (! isset($totals[$currency]) || ((float) $totals[$currency]['in'] == 0 && (float) $totals[$currency]['out'] == 0)) {
                continue;
            }

            $total = $totals[$currency];
            $lines[] = __('messages.cashbook_in_total').': '.number_format((float) $total['in'], 2).' '.$currency;
            $lines[] = __('messages.cashbook_out_total').': '.number_format((float) $total['out'], 2).' '.$currency;
            $netLabel = $total['net'] >= 0 ? '+' : '-';
            $lines[] = __('messages.net_balance').': '.$netLabel.number_format(abs((float) $total['net']), 2).' '.$currency;
            $lines[] = str_repeat('-', 30);
        }

        foreach ($transactions as $tx) {
            $sign = $tx->direction === 'in' ? '+' : '-';
            $lines[] = $tx->date->format('d M Y')
                .' | '.$tx->label
                .' | '.$sign.number_format((float) $tx->amount, 2).' '.$tx->currency;
            if (! empty($tx->notes)) {
                $lines[] = '  ('.$tx->notes.')';
            }
        }

        $lines[] = str_repeat('=', 30);
        $lines[] = __('messages.business_mgmt_system').' (MGS)';

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
            'remindable_type' => $party?->getMorphClass(),
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

    /**
     * Send a rendered PDF (invoice / purchase bill / cashbook statement) to
     * the party over WhatsApp and log it in the chat history exactly like the
     * plain-text bills: one Reminder row per document, with the stored copy
     * linked as media so the chat bubble can open it again.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function sendPdf(
        ?Model $party,
        string $phone,
        string $binary,
        string $filename,
        string $caption = '',
        ?float $amount = null,
        string $currency = 'AFN',
    ): array {
        if (empty(trim($phone))) {
            return ['ok' => false, 'error' => __('messages.no_phone_to_send')];
        }

        // Keep the exact bytes that went out — the chat history should be able
        // to reopen the document without re-rendering it.
        $relative = 'whatsapp-outbox/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($relative, $binary);

        $reminder = Reminder::create([
            'user_id' => auth()->id(),
            'remindable_type' => $party?->getMorphClass(),
            'remindable_id' => $party?->id,
            'amount' => $amount,
            'currency' => $currency ?: 'AFN',
            'channel' => 'whatsapp',
            'message' => $caption,
            'media_path' => $relative,
            'media_type' => 'application/pdf',
            'status' => 'pending',
        ]);

        try {
            // Documents are gateway-only: the Meta Cloud fallback the text
            // bills use has no media upload path wired up.
            if (! $this->openWaService || ! $this->openWaService->isConfigured()) {
                $reminder->update([
                    'status' => 'failed',
                    'error_message' => __('messages.openwa_unreachable'),
                ]);

                return ['ok' => false, 'error' => __('messages.pdf_send_failed')];
            }

            $sessionState = $this->openWaService->sessionStatus()['status'] ?? null;

            if ($sessionState !== 'ready' && $sessionState !== 'connected') {
                $reminder->update([
                    'status' => 'failed',
                    'error_message' => "WhatsApp session not connected (state: {$sessionState}). Link the device in Settings → WhatsApp Gateway.",
                ]);

                return ['ok' => false, 'error' => __('messages.pdf_send_failed')];
            }

            $sent = $this->openWaService->sendDocument(
                $phone,
                Storage::disk('local')->path($relative),
                $filename,
                $caption,
            );

            $reminder->update([
                'status' => $sent['ok'] ? 'sent' : 'failed',
                'sent_at' => $sent['ok'] ? now() : null,
                'provider_message_id' => $sent['id'],
                'error_message' => $sent['ok'] ? null : 'WhatsApp gateway rejected the document.',
            ]);

            return $sent['ok']
                ? ['ok' => true, 'error' => null]
                : ['ok' => false, 'error' => __('messages.pdf_send_failed')];
        } catch (\Exception $e) {
            Log::error('WhatsApp document send failed', [
                'reminder_id' => $reminder->id,
                'error' => $e->getMessage(),
            ]);

            $reminder->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => __('messages.pdf_send_failed')];
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
            $lines[] = __('messages.phone').': '.$company['phone'];
        }

        if ($company['email']) {
            $lines[] = __('messages.email').': '.$company['email'];
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
            $lines[] = __('messages.phone').': '.$party->phone;
        }

        if ($party->address) {
            $lines[] = __('messages.address').': '.$party->address;
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

    /**
     * Wrap an amount/equation run in Unicode bidi isolates (LRI…PDI) so the
     * plain-text bill keeps its logical order when an RTL locale (ps/fa)
     * renders it — both in the in-app chat view and in real WhatsApp.
     */
    private function isolated(\Closure $render): string
    {
        return "\u{2066}".$render()."\u{2069}";
    }
}
