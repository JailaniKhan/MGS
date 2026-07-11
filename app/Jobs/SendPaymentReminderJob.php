<?php

namespace App\Jobs;

use App\Services\Sms\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPaymentReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $phone,
        public string $message,
    ) {}

    public function handle(SmsGateway $smsGateway): void
    {
        $smsGateway->send($this->phone, $this->message);
    }
}
