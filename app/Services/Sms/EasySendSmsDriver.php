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
            Log::info('SMS skipped (no API key configured)', compact('phone', 'message'));

            return false;
        }

        $phone = $this->normalizePhone($phone);

        $response = Http::post($url, [
            'api_key' => $apiKey,
            'to' => $phone,
            'from' => $sender,
            'message' => $message,
        ]);

        if ($response->successful()) {
            return true;
        }

        Log::error('SMS send failed', [
            'phone' => $phone,
            'response' => $response->body(),
        ]);

        return false;
    }

    /**
     * Normalize an Afghan phone number: strip non-digits and ensure the
     * country code (93) is present so local numbers can be delivered.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if ($phone === '') {
            return $phone;
        }

        if (!str_starts_with($phone, '93')) {
            $phone = '93' . ltrim($phone, '0');
        }

        return $phone;
    }
}
