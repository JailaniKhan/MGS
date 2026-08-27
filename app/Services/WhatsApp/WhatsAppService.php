<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function __construct(
        private OpenWaService $openWa,
    ) {}

    public function send(string $phone, string $message): bool
    {
        // Prefer the self-hosted OpenWA gateway when configured.
        if ($this->openWa && $this->openWa->isConfigured()) {
            return $this->openWa->send($phone, $message);
        }

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

    /**
     * Send text through the OpenWA gateway, surfacing the provider id.
     *
     * @return array{ok: bool, id: ?string}
     */
    public function openWaSendText(string $phone, string $message): array
    {
        if ($this->openWa && $this->openWa->isConfigured()) {
            return $this->openWa->sendText($phone, $message);
        }

        Log::info('WhatsApp skipped (not configured)', compact('phone', 'message'));

        return ['ok' => false, 'id' => null];
    }

    /**
     * Send a voice note (PTT). Voice requires the self-hosted OpenWA
     * gateway — the Meta Cloud API path is not implemented for media.
     *
     * @return array{ok: bool, id: ?string}
     */
    public function sendVoice(string $phone, string $filePath, bool $ptt = true): array
    {
        if ($this->openWa && $this->openWa->isConfigured()) {
            return $this->openWa->sendVoice($phone, $filePath, $ptt);
        }

        Log::info('WhatsApp voice skipped (not configured)', compact('phone', 'filePath'));

        return ['ok' => false, 'id' => null];
    }
}
