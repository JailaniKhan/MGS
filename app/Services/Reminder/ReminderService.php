<?php

namespace App\Services\Reminder;

use App\Models\Reminder;
use App\Services\WhatsApp\OpenWaService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    public function __construct(
        private AIMessageGenerator $messageGenerator,
        private WhatsAppService $whatsAppService,
        private OpenWaService $openWaService,
    ) {}

    /**
     * For WhatsApp: generates the message, creates a reminder record, and sends via WhatsApp API.
     * For SMS: only generates the message — the actual send happens client-side via the
     * device's native SMS app (sms: URI deep link). The message is returned; no record is stored.
     */
    public function sendReminder(
        string $remindableType,
        int $remindableId,
        string $name,
        string $phone,
        string $amount,
        string $currency,
        string $channel = 'sms',
        string $dueDate = '',
        ?int $userId = null,
    ): Reminder {
        $message = $this->messageGenerator->generate($name, $amount, $currency, $dueDate);

        if ($channel === 'whatsapp') {
            $reminder = Reminder::create([
                'user_id' => $userId,
                'remindable_type' => $remindableType,
                'remindable_id' => $remindableId,
                'amount' => $amount,
                'currency' => $currency,
                'channel' => $channel,
                'message' => $message,
                'status' => 'pending',
            ]);

            try {
                // Only the self-hosted OpenWA gateway has a session/connection
                // state to check. The Meta Cloud API client sends directly.
                if ($this->openWaService && $this->openWaService->isConfigured()) {
                    $status = $this->openWaService->sessionStatus();
                    $sessionState = $status['status'] ?? null;

                    if ($sessionState !== 'ready' && $sessionState !== 'connected') {
                        $reminder->update([
                            'status' => 'failed',
                            'error_message' => "WhatsApp session not connected (state: {$sessionState}). Link the device in Settings → WhatsApp Gateway.",
                        ]);

                        return $reminder->fresh();
                    }
                }

                $sent = $this->whatsAppService?->send($phone, $message) ?? false;

                $reminder->update([
                    'status' => $sent ? 'sent' : 'failed',
                    'sent_at' => $sent ? now() : null,
                    'error_message' => $sent ? null : 'WhatsApp gateway rejected the message.',
                ]);
            } catch (\Exception $e) {
                Log::error('WhatsApp send failed', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage(),
                ]);

                $reminder->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            return $reminder->fresh();
        }

        // SMS: return a lightweight object with the message & status for the frontend
        // to open the native SMS app. No API call, no DB record.
        $reminder = new Reminder();
        $reminder->message = $message;
        $reminder->status = 'drafted';
        $reminder->phone = $phone;

        return $reminder;
    }
}
