<?php

namespace App\Services\Reminder;

use App\Models\Reminder;
use App\Services\Sms\SmsGateway;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    public function __construct(
        private AIMessageGenerator $messageGenerator,
        private SmsGateway $smsGateway,
        private ?WhatsAppService $whatsAppService = null,
    ) {}

    public function sendReminder(
        string $remindableType,
        int $remindableId,
        string $name,
        string $phone,
        string $amount,
        string $currency,
        string $channel = 'sms',
        string $dueDate = '',
    ): Reminder {
        $message = $this->messageGenerator->generate($name, $amount, $currency, $dueDate);

        $reminder = Reminder::create([
            'remindable_type' => $remindableType,
            'remindable_id' => $remindableId,
            'amount' => $amount,
            'currency' => $currency,
            'channel' => $channel,
            'message' => $message,
            'status' => 'pending',
        ]);

        try {
            $sent = false;

            if ($channel === 'whatsapp' && $this->whatsAppService) {
                $sent = $this->whatsAppService->send($phone, $message);
            } else {
                $sent = $this->smsGateway->send($phone, $message);
            }

            if ($sent) {
                $reminder->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $reminder->update([
                    'status' => 'failed',
                    'error_message' => 'Provider returned failure',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Reminder send failed', [
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
}
