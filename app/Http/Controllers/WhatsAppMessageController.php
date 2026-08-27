<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Reminder;
use App\Models\Supplier;
use App\Services\WhatsApp\OpenWaService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Free-form outgoing messages (custom text / voice notes) to a single
 * customer or supplier, composed from the WhatsApp chats hub. Every send —
 * successful or not — is logged as a whatsapp-channel Reminder so the
 * conversation history stays complete.
 */
class WhatsAppMessageController extends Controller
{
    /** Audio containers accepted for voice notes, mapped to file extensions. */
    private const AUDIO_EXTENSIONS = [
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/opus' => 'ogg',
        'application/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/amr' => 'amr',
    ];

    public function storeCustomer(Request $request, Customer $customer, WhatsAppService $whatsapp): JsonResponse
    {
        return $this->handle($request, $customer, 'customer', $whatsapp);
    }

    public function storeSupplier(Request $request, Supplier $supplier, WhatsAppService $whatsapp): JsonResponse
    {
        return $this->handle($request, $supplier, 'supplier', $whatsapp);
    }

    /**
     * Stream a sent voice note back for in-chat playback.
     */
    public function media(Request $request, Reminder $reminder)
    {
        abort_unless($reminder->channel === 'whatsapp' && $reminder->media_path, 404);
        abort_unless($reminder->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk('local')->exists($reminder->media_path), 404);

        return Storage::disk('local')->response(
            $reminder->media_path,
            basename($reminder->media_path),
            ['Content-Type' => $reminder->media_type ?: 'application/octet-stream']
        );
    }

    private function handle(Request $request, $contact, string $type, WhatsAppService $whatsapp): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:text,voice',
            'message' => 'required_if:type,text|nullable|string|max:4096',
            // Data-URL/base64 audio: the NativePHP WebView interceptor
            // corrupts multipart bodies, so the compose UI posts JSON.
            'audio' => 'required_if:type,voice|nullable|string',
            'duration' => 'nullable|integer|min:0|max:3600',
        ]);

        if (empty($contact->phone)) {
            return response()->json([
                'ok' => false,
                'message' => __('messages.wa_contact_no_phone'),
            ], 422);
        }

        $isVoice = $data['type'] === 'voice';

        if ($isVoice) {
            [$filePath] = $this->storeAudio((string) $data['audio']);
            // Derive from the stored file itself — browsers report loose
            // mimics ("audio/webm" with no codecs hint).
            $mediaType = OpenWaService::audioMimeType($filePath);
            $result = $whatsapp->sendVoice($contact->phone, $filePath);
        } else {
            $filePath = null;
            $mediaType = null;
            $result = $whatsapp->openWaSendText($contact->phone, trim((string) $data['message']));
        }

        $reminder = Reminder::create([
            'user_id' => $request->user()->id,
            'remindable_type' => $type,
            'remindable_id' => $contact->id,
            'amount' => null,
            'currency' => 'AFN',
            'channel' => 'whatsapp',
            'message' => $isVoice ? '' : trim((string) $data['message']),
            'media_path' => $isVoice ? 'whatsapp-outbox/' . basename($filePath) : null,
            'media_type' => $mediaType,
            'status' => $result['ok'] ? 'sent' : 'failed',
            'provider_message_id' => $result['id'],
            'error_message' => $result['ok'] ? null : __('messages.openwa_unreachable'),
            'sent_at' => now(),
        ]);

        if ($contact instanceof Customer && $result['ok']) {
            $contact->updateQuietly(['last_contacted_at' => now()]);
        }

        return response()->json([
            'ok' => $result['ok'],
            'status' => $result['ok'] ? 'sent' : 'failed',
            'id' => $reminder->id,
            'time_label' => now()->format('H:i'),
            'media_url' => $reminder->media_path ? route('whatsapp.chats.media', $reminder) : null,
            'media_type' => $mediaType,
        ]);
    }

    /**
     * Persist an uploaded voice recording under storage/app/whatsapp-outbox.
     *
     * @return array{0: string, 1: string} absolute path + MIME type
     */
    private function storeAudio(string $payload): array
    {
        [$mime, $binary] = $this->decodeDataUrl($payload);

        abort_unless($binary !== '' && isset(self::AUDIO_EXTENSIONS[$mime]), 422, __('messages.wa_audio_invalid'));

        // Keep the DB value platform-neutral ("whatsapp-outbox/x.webm") and
        // derive the absolute path through the storage disk.
        $relative = 'whatsapp-outbox/' . Str::uuid() . '.' . self::AUDIO_EXTENSIONS[$mime];
        Storage::disk('local')->put($relative, $binary);

        return [Storage::disk('local')->path($relative), $mime];
    }

    /**
     * Split "data:<mime>;base64,<payload>" into MIME + raw bytes.
     *
     * @return array{0: string, 1: string}
     */
    private function decodeDataUrl(string $payload): array
    {
        if (preg_match('/^data:([^;,]+)(?:;[^,]*)?,/', $payload, $m)) {
            return [$m[1], (string) base64_decode(substr($payload, strlen($m[0])), true)];
        }

        // Bare base64 without a data-URL prefix — assume WebM from our UI.
        $binary = (string) base64_decode($payload, true);

        return [$binary !== '' ? 'audio/webm' : '', $binary];
    }
}
