<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
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

        if (! preg_match('/^\d{6,15}$/', $phone)) {
            return $phone.'@c.us';
        }

        return '93'.$phone.'@c.us';
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
        return ! empty($this->apiKey());
    }

    /**
     * Send a text message to a single recipient.
     */
    public function send(string $phone, string $message): bool
    {
        return $this->sendText($phone, $message)['ok'];
    }

    /**
     * Send a text message, surfacing the gateway's provider message id so
     * callers can log it (reminders.provider_message_id).
     *
     * @return array{ok: bool, id: ?string}
     */
    public function sendText(string $phone, string $message): array
    {
        if (! $this->isConfigured()) {
            Log::info('OpenWA skipped (not configured)', compact('phone', 'message'));

            return ['ok' => false, 'id' => null];
        }

        $chatId = $this->toChatId($phone);

        try {
            $response = $this->client()->post(
                "/api/sessions/{$this->session()}/messages/send-text",
                ['chatId' => $chatId, 'text' => $message]
            );
        } catch (ConnectionException $e) {
            Log::error('OpenWA unreachable', [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'id' => null];
        }

        if ($response->successful()) {
            return ['ok' => true, 'id' => data_get($response->json(), 'id')];
        }

        Log::error('OpenWA send failed', [
            'chatId' => $chatId,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        return ['ok' => false, 'id' => null];
    }

    /**
     * Send the same message to multiple recipients.
     *
     * @param  array<int, string>  $phones
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
     * Send a voice note (PTT) from a local audio file.
     *
     * The audio travels base64-encoded inside the JSON body: the gateway
     * decodes it to a temp file for Baileys. OGG/Opus and WebM/Opus render
     * as push-to-talk bubbles; other formats are sent as plain audio.
     *
     * @return array{ok: bool, id: ?string}
     */
    public function sendVoice(string $phone, string $filePath, bool $ptt = true): array
    {
        if (! $this->isConfigured()) {
            Log::info('OpenWA voice skipped (not configured)', compact('phone', 'filePath'));

            return ['ok' => false, 'id' => null];
        }

        if (! is_file($filePath)) {
            Log::error('OpenWA voice file missing', compact('filePath'));

            return ['ok' => false, 'id' => null];
        }

        $chatId = $this->toChatId($phone);
        $mime = self::audioMimeType($filePath);
        // Parameters ("; codecs=opus") stay out of the data URL header —
        // they travel in the explicit mimetype field instead.
        $baseMime = trim(explode(';', $mime, 2)[0]);

        try {
            $response = $this->client()->post(
                "/api/sessions/{$this->session()}/messages/send-voice",
                [
                    'chatId' => $chatId,
                    'audio' => 'data:'.$baseMime.';base64,'.base64_encode((string) file_get_contents($filePath)),
                    'ptt' => $ptt,
                    'mimetype' => $mime,
                ]
            );
        } catch (ConnectionException $e) {
            Log::error('OpenWA unreachable (voice)', [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'id' => null];
        }

        if ($response->successful()) {
            return ['ok' => true, 'id' => data_get($response->json(), 'id')];
        }

        Log::error('OpenWA voice send failed', [
            'chatId' => $chatId,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        return ['ok' => false, 'id' => null];
    }

    /**
     * Send an image from a local file, with an optional caption.
     *
     * The gateway accepts a data URL (base64) in the `image` field — the
     * same JSON-safe path voice notes use, for the same NativePHP WebView
     * reason.
     *
     * @return array{ok: bool, id: ?string}
     */
    public function sendImage(string $phone, string $filePath, ?string $caption = null): array
    {
        if (! $this->isConfigured()) {
            Log::info('OpenWA image skipped (not configured)', compact('phone', 'filePath'));

            return ['ok' => false, 'id' => null];
        }

        if (! is_file($filePath)) {
            Log::error('OpenWA image file missing', compact('filePath'));

            return ['ok' => false, 'id' => null];
        }

        $chatId = $this->toChatId($phone);
        $mime = image_type_to_mime_type(exif_imagetype($filePath) ?: false) ?: 'image/jpeg';

        try {
            $response = $this->client()->post(
                "/api/sessions/{$this->session()}/messages/send-image",
                [
                    'chatId' => $chatId,
                    'image' => 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($filePath)),
                    'caption' => (string) $caption,
                ]
            );
        } catch (ConnectionException $e) {
            Log::error('OpenWA unreachable (image)', [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'id' => null];
        }

        if ($response->successful()) {
            return ['ok' => true, 'id' => data_get($response->json(), 'id')];
        }

        Log::error('OpenWA image send failed', [
            'chatId' => $chatId,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        return ['ok' => false, 'id' => null];
    }

    /**
     * Send a document (printed invoice / purchase bill / cashbook statement)
     * as a WhatsApp attachment.
     *
     * Like voice notes and images the bytes ride inside the JSON body as a
     * data URL: the NativePHP WebView's fetch interceptor corrupts
     * multipart/binary payloads, so JSON + base64 is the only reliable path.
     * The gateway decodes it and Baileys uploads it as a real document.
     *
     * @return array{ok: bool, id: ?string}
     */
    public function sendDocument(string $phone, string $filePath, ?string $fileName = null, ?string $caption = null): array
    {
        if (! $this->isConfigured()) {
            Log::info('OpenWA document skipped (not configured)', compact('phone', 'filePath'));

            return ['ok' => false, 'id' => null];
        }

        if (! is_file($filePath)) {
            Log::error('OpenWA document file missing', compact('filePath'));

            return ['ok' => false, 'id' => null];
        }

        // The gateway's send-document endpoint takes no caption, so a caption
        // travels as a short text message right before the file. Best effort:
        // a failed caption must never stop the document itself.
        if ($caption !== null && trim($caption) !== '') {
            $this->sendText($phone, $caption);
        }

        $chatId = $this->toChatId($phone);
        $fileName = $fileName ?: basename($filePath);
        $mime = self::documentMimeType($fileName);

        try {
            $response = $this->client()->post(
                "/api/sessions/{$this->session()}/messages/send-document",
                [
                    'chatId' => $chatId,
                    'document' => 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($filePath)),
                    'mimetype' => $mime,
                    'fileName' => $fileName,
                ]
            );
        } catch (ConnectionException $e) {
            Log::error('OpenWA unreachable (document)', [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'id' => null];
        }

        if ($response->successful()) {
            return ['ok' => true, 'id' => data_get($response->json(), 'id')];
        }

        Log::error('OpenWA document send failed', [
            'chatId' => $chatId,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        return ['ok' => false, 'id' => null];
    }

    /**
     * MIME type for an outgoing document, from its extension.
     */
    public static function documentMimeType(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    /**
     * Best-effort MIME type for an audio file. Extension mapping wins over
     * content sniffing because finfo reports "video/webm" for WebM audio,
     * which WhatsApp refuses to play.
     */
    public static function audioMimeType(string $path): string
    {
        static $byExtension = [
            'webm' => 'audio/webm; codecs=opus',
            'ogg' => 'audio/ogg; codecs=opus',
            'oga' => 'audio/ogg; codecs=opus',
            'opus' => 'audio/ogg; codecs=opus',
            'm4a' => 'audio/mp4',
            'mp4' => 'audio/mp4',
            'aac' => 'audio/aac',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'wave' => 'audio/wav',
            'amr' => 'audio/amr',
        ];

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && isset($byExtension[$ext])) {
            return $byExtension[$ext];
        }

        $detected = false;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = (string) finfo_file($finfo, $path);
                finfo_close($finfo);
            }
        }

        // Sniffed container types are normalised to their audio flavour.
        if ($detected === 'video/webm') {
            return 'audio/webm; codecs=opus';
        }
        if ($detected === 'video/ogg' || $detected === 'application/ogg') {
            return 'audio/ogg; codecs=opus';
        }

        return ($detected && str_starts_with($detected, 'audio/')) ? $detected : 'audio/ogg; codecs=opus';
    }

    /**
     * Get the session status. Useful to know if the QR has been scanned.
     *
     * @return array|null Array of session info, or null if not configured /
     *                    gateway unreachable / request failed.
     */
    public function sessionStatus(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client()->get("/api/sessions/{$this->session()}");
        } catch (ConnectionException $e) {
            Log::warning('OpenWA unreachable (sessionStatus)', ['error' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Poll inbound messages the gateway has buffered since the given
     * sequence number (GET /api/sessions/:id/messages?since=).
     *
     * The cursor is persisted via cacheCursor()/cursor() so successive runs
     * only see fresh messages; the sequence survives gateway restarts only
     * within its in-memory buffer (a gateway restart resets it to 0, so the
     * cursor is also reset whenever a lower-than-previous cursor arrives).
     *
     * @return array{messages: array<int, array>, cursor: int}|null
     */
    public function messages(int $since = 0): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client()->get("/api/sessions/{$this->session()}/messages", [
                'since' => $since,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('OpenWA unreachable (messages)', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return [
            'messages' => (array) data_get($response->json(), 'messages', []),
            'cursor' => (int) data_get($response->json(), 'cursor', $since),
        ];
    }

    /**
     * Get the QR code (base64 PNG) to scan with WhatsApp.
     */
    public function qrCode(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client()->get("/api/sessions/{$this->session()}/qr");
        } catch (ConnectionException $e) {
            Log::warning('OpenWA unreachable (qrCode)', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return $data['qrCode'] ?? $data['qr'] ?? $data['base64'] ?? $data['data'] ?? null;
    }

    /**
     * Request an 8-character pairing code to link the session by phone number
     * (alternative to scanning the QR code).
     *
     * @return array{pairingCode: string, expiresAt: int}|null The code plus
     *                                                         its expiry timestamp (ms epoch), or null on failure.
     */
    public function requestPairingCode(string $phone): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Strip non-digits; ensure a country code is present (default 93 = Afghanistan).
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }
        if (! str_starts_with($phone, '93')) {
            $phone = '93'.$phone;
        }

        try {
            $response = $this->client()->post(
                "/api/sessions/{$this->session()}/pairing-code",
                ['phoneNumber' => $phone]
            );
        } catch (ConnectionException $e) {
            Log::warning('OpenWA unreachable (pairing code)', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::error('OpenWA pairing code request failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        return [
            'pairingCode' => $data['pairingCode'] ?? $data['pairing_code'] ?? null,
            'expiresAt' => $data['expiresAt'] ?? null,
        ];
    }

    /**
     * Poll the gateway for the CURRENT pairing code. Returns the code +
     * expiry (ms epoch) while it is still valid, or null when it has expired
     * (or the session was restarted) so the UI can auto-request a fresh one.
     *
     * @return array{pairingCode: string, expiresAt: int}|null
     */
    public function pairingStatus(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client()->get("/api/sessions/{$this->session()}/pairing-code");
        } catch (ConnectionException $e) {
            Log::warning('OpenWA unreachable (pairing status)', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return [
            'pairingCode' => $data['pairingCode'] ?? null,
            'expiresAt' => $data['expiresAt'] ?? null,
        ];
    }

    /**
     * Register a webhook so OpenWA pushes inbound messages/events to your app.
     */
    public function registerWebhook(string $url, array $events = ['message.received'], ?string $secret = null): bool
    {
        if (! $this->isConfigured()) {
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
        } catch (ConnectionException $e) {
            Log::warning('OpenWA unreachable (register webhook)', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->successful();
    }
}
