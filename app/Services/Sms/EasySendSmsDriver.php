<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EasySendSmsDriver implements SmsGateway
{
    public function send(string $phone, string $message): bool
    {
        $apiKey = config('services.sms.api_key');
        $sender = config('services.sms.sender');
        $url = config('services.sms.url', 'https://api.easysendsms.com/bulksms');

        if (empty($apiKey)) {
            Log::info('SMS skipped (no API key)', compact('phone', 'message'));

            return true;
        }

        $response = Http::post($url, [
            'api_key' => $apiKey,
            'to' => $phone,
            'from' => $sender,
            'message' => $message,
        ]);

        return $response->successful();
    }
}
