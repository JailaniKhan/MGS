<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\OpenWaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives inbound webhooks from the self-hosted OpenWA gateway.
 * OpenWA signs payloads with HMAC-SHA256 using the webhook secret.
 */
class OpenWaWebhookController extends Controller
{
    public function handle(Request $request, OpenWaService $openWa)
    {
        if (!empty($secret = config('services.openwa.webhook_secret'))) {
            $signature = $request->header('X-OpenWA-Signature') ?: $request->header('X-Hub-Signature-256');
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

            if (!hash_equals($expected, (string) $signature)) {
                return response()->json(['error' => 'invalid signature'], 401);
            }
        }

        $event = $request->input('event') ?? $request->input('type') ?? 'unknown';
        $payload = $request->all();

        Log::info('OpenWA webhook received', ['event' => $event]);

        // TODO: dispatch to your business logic, e.g. create an order note,
        // auto-reply, or store the inbound message. The payload shape depends
        // on the event; inspect $payload to map fields like:
        //   $payload['data']['from']  -> sender chatId (e.g. 923001234567@c.us)
        //   $payload['data']['body']  -> message text
        //   $payload['data']['fromMe']-> bool

        return response()->json(['ok' => true]);
    }
}
