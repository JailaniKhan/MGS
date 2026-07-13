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
        $url = config('services.sms.url', 'https://restapi.easysendsms.app/v1/rest/sms/send');

        if (empty($apiKey)) {
            Log::info('SMS skipped (no API key configured)', compact('phone', 'message'));

            return false;
        }

        $phone = $this->normalizePhone($phone);

        $response = Http::withHeaders([
            'apikey' => $apiKey,
            'Accept' => 'application/json',
        ])->post($url, [
            'from' => $sender,
            'to' => $phone,
            'text' => $message,
            'type' => $this->messageType($message),
        ]);

        $body = $response->json();

        $messageIds = $body['messageIds'] ?? [];
        $hasError = !empty($body['error']) || collect($messageIds)->contains(fn ($id) => str_starts_with((string) $id, 'ERR:'));

        if ($response->successful() && !$hasError) {
            Log::info('SMS accepted by provider', [
                'phone' => $phone,
                'message_ids' => $messageIds,
            ]);

            return true;
        }

        Log::error('SMS send failed', [
            'phone' => $phone,
            'response' => $response->body(),
        ]);

        return false;
    }

    /**
     * Pick the encoding type: unicode for any non-GSM-7 content
     * (e.g. Dari/Pashto or the Afghani sign), otherwise text.
     */
    protected function messageType(string $message): string
    {
        return $this->isGsm7($message) ? 'text' : 'unicode';
    }

    protected function isGsm7(string $message): bool
    {
        $gsm = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

        $length = mb_strlen($message);

        for ($i = 0; $i < $length; $i++) {
            if (mb_strpos($gsm, mb_substr($message, $i, 1)) === false) {
                return false;
            }
        }

        return true;
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
