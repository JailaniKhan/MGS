<?php

namespace App\Services\Sms;

interface SmsGateway
{
    public function send(string $phone, string $message): bool;
}
