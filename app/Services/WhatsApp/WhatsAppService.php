<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function send(string $phone, string $message): bool
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $url = config('services.whatsapp.url', 'https://graph.facebook.com/v18.0');

        if (empty($token) || empty($phoneNumberId)) {
            Log::info('WhatsApp skipped (not configured)', compact('phone', 'message'));
            return false;
        }

        $phone = ltrim($phone, '+');
        if (!str_starts_with($phone, '93')) {
            $phone = '93' . ltrim($phone, '0');
        }

        $response = Http::withToken($token)
            ->post("{$url}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        if ($response->successful()) {
            return true;
        }

        Log::error('WhatsApp send failed', [
            'phone' => $phone,
            'response' => $response->body(),
        ]);

        return false;
    }
}
