<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EasySendSmsDriver implements SmsGateway
{
    public function send(string $phone, string $message): bool
    {
        $apiKey = config('services.sms.api_key');
        $sender = $this->resolveSender();
        $url = config('services.sms.url', 'https://restapi.easysendsms.app/v1/rest/sms/send');

        if (empty($apiKey)) {
            Log::info('SMS skipped (no API key configured)', compact('phone', 'message'));

            return false;
        }

        if (empty($sender)) {
            Log::error('SMS send failed: no sender ID configured. Set SMS_SENDER in .env or "sms_sender" in Settings (must be an EasySendSMS-approved sender).', compact('phone'));

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
            // A proper delivery receipt is a UUID ("OK: <uuid>"). A bare numeric id
            // (e.g. "4015") means the gateway accepted submission but the message may
            // not be delivered — usually because the sender ID is not approved by the
            // recipient carrier. Log a warning so the issue is visible.
            $accepted = collect($messageIds)->contains(fn ($id) => str_starts_with((string) $id, 'OK:'));

            Log::info('SMS accepted by provider', [
                'phone' => $phone,
                'sender' => $sender,
                'message_ids' => $messageIds,
                'delivery_receipt' => $accepted,
            ]);

            if (!$accepted) {
                Log::warning('SMS accepted but no delivery receipt returned — the sender ID "' . $sender . '" may not be approved by the carrier. The message might not reach the handset. Configure an EasySendSMS-approved sender (Settings -> SMS Sender).', compact('phone'));
            }

            return true;
        }

        Log::error('SMS send failed', [
            'phone' => $phone,
            'response' => $response->body(),
        ]);

        return false;
    }

    /**
     * Resolve the sender ID: a Setting (so it can be changed from the UI without
     * editing .env) takes priority, otherwise fall back to the config value.
     */
    protected function resolveSender(): string
    {
        $setting = \App\Models\Setting::get('sms_sender');

        return !empty($setting) ? (string) $setting : (string) config('services.sms.sender', '');
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
