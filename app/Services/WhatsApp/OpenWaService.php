<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenWA (self-hosted WhatsApp API gateway) client.
 *
 * API docs: https://github.com/rmyndharis/OpenWA
 * All requests require the X-API-Key header. Recipients use the chatId
 * format "<cc><number>@c.us" (e.g. 923001234567@c.us).
 */
class OpenWaService
{
    protected function baseUrl(): string
    {
        return rtrim(config('services.openwa.base_url'), '/');
    }

    protected function apiKey(): ?string
    {
        return config('services.openwa.api_key');
    }

    protected function session(): string
    {
        return config('services.openwa.session', 'default');
    }

    protected function client()
    {
        return Http::withHeader('X-API-Key', $this->apiKey())
            ->acceptJson()
            ->baseUrl($this->baseUrl());
    }

    /**
     * Convert a raw phone number to OpenWA chatId.
     * Strips "+" and leading zeros, ensures country code (93 = Afghanistan default).
     */
    public function toChatId(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Strip any leading country code or local prefix so we can rebuild it
        // consistently (93 = Afghanistan, 0 = local prefix).
        if (str_starts_with($phone, '93')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        if (!preg_match('/^\d{6,15}$/', $phone)) {
            return $phone . '@c.us';
        }

        return '93' . $phone . '@c.us';
    }

    /**
     * Reverse a WhatsApp chatId (e.g. "93700123456@c.us") back to a phone
     * number. Used when handling inbound webhooks where the sender arrives
     * as a chatId and we need to look up the matching customer record.
     * Returns digits only (no "+"), or null if the chatId is malformed.
     */
    public static function chatIdToPhone(string $chatId): ?string
    {
        // Strip the "@c.us" / "@g.us" / "@lid" suffix
        $atPos = strpos($chatId, '@');
        if ($atPos !== false) {
            $chatId = substr($chatId, 0, $atPos);
        }

        $digits = preg_replace('/[^0-9]/', '', $chatId);

        return $digits !== '' ? $digits : null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey());
    }

    /**
     * Send a text message to a single recipient.
     */
    public function send(string $phone, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::info('OpenWA skipped (not configured)', compact('phone', 'message'));
            return false;
        }

        $chatId = $this->toChatId($phone);

        try {
            $response = $this->client()->post(
                "/api/sessions/{$this->session()}/messages/send-text",
                ['chatId' => $chatId, 'text' => $message]
            );
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenWA unreachable', [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        Log::error('OpenWA send failed', [
            'chatId' => $chatId,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        return false;
    }

    /**
     * Send the same message to multiple recipients.
     *
     * @param array<int, string> $phones
     */
    public function sendBulk(array $phones, string $message): array
    {
        $results = [];

        foreach ($phones as $phone) {
            $results[$phone] = $this->send($phone, $message);
        }

        return $results;
    }

    /**
     * Get the session status. Useful to know if the QR has been scanned.
     *
     * @return array|null Array of session info, or null if not configured /
     *         gateway unreachable / request failed.
     */
    public function sessionStatus(): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client()->get("/api/sessions/{$this->session()}");
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('OpenWA unreachable (sessionStatus)', ['error' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Get the QR code (base64 PNG) to scan with WhatsApp.
     */
    public function qrCode(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client()->get("/api/sessions/{$this->session()}/qr");
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('OpenWA unreachable (qrCode)', ['error' => $e->getMessage()]);

            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        return $data['qrCode'] ?? $data['qr'] ?? $data['base64'] ?? $data['data'] ?? null;
    }

    /**
     * Request an 8-character pairing code to link the session by phone number
     * (alternative to scanning the QR code).
     *
     * @return string|null The pairing code, or null on failure.
     */
    public function requestPairingCode(string $phone): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        // Strip non-digits; ensure a country code is present (default 93 = Afghanistan).
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }
        if (!str_starts_with($phone, '93')) {
            $phone = '93' . $phone;
        }

        try {
            $response = $this->client()->post(
                "/api/sessions/{$this->session()}/pairing-code",
                ['phoneNumber' => $phone]
            );
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('OpenWA unreachable (pairing code)', ['error' => $e->getMessage()]);

            return null;
        }

        if (!$response->successful()) {
            Log::error('OpenWA pairing code request failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        return $data['pairingCode'] ?? $data['pairing_code'] ?? null;
    }

    /**
     * Register a webhook so OpenWA pushes inbound messages/events to your app.
     */
    public function registerWebhook(string $url, array $events = ['message.received'], ?string $secret = null): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $payload = [
            'url' => $url,
            'events' => $events,
        ];

        if ($secret) {
            $payload['secret'] = $secret;
        }

        try {
            $response = $this->client()->post(
                "/api/sessions/{$this->session()}/webhooks",
                $payload
            );
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('OpenWA unreachable (register webhook)', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->successful();
    }
}
