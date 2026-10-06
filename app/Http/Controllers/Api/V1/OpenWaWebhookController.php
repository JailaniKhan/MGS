<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
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
        $secret = config('services.openwa.webhook_secret');

        // Fail closed: refuse to process webhooks when no secret is configured.
        // Returning 404 keeps the route indistinguishable from a non-existent
        // endpoint so attackers can't probe for an unauthenticated handler.
        if (empty($secret)) {
            Log::critical('OpenWA webhook rejected: OPENWA_WEBHOOK_SECRET is not set');
            abort(404);
        }

        $signature = $request->header('X-OpenWA-Signature') ?: $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, (string) $signature)) {
            Log::warning('OpenWA webhook rejected: invalid signature');

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $event = $request->input('event') ?? $request->input('type') ?? 'unknown';
        $payload = $request->all();

        Log::info('OpenWA webhook received', ['event' => $event]);

        // Auto-mark the customer as contacted when they send any inbound message.
        if ($event === 'message.received') {
            $this->markCustomerAsContacted($payload);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Look up the customer by the inbound sender's chatId and stamp
     * last_contacted_at so the reminder loop stops nagging them today.
     */
    protected function markCustomerAsContacted(array $payload): void
    {
        try {
            $chatId = data_get($payload, 'data.from') ?? data_get($payload, 'data.author');
            if (! $chatId) {
                return;
            }

            $phone = OpenWaService::chatIdToPhone((string) $chatId);
            if (! $phone) {
                return;
            }

            // Build the set of plausible phone formats the customer row may store:
            //   +93700123456 | 93700123456 | 0700123456 | 700 12 34 56
            $digits = preg_replace('/[^0-9]/', '', $phone);
            $local = $digits;
            if (str_starts_with($local, '93')) {
                $local = '0'.substr($local, 2);
            }

            $needles = array_unique(array_filter([
                $phone,
                $digits,
                $local,
                '+'.$digits,
            ]));

            $query = Customer::query();
            foreach ($needles as $i => $needle) {
                $method = $i === 0 ? 'where' : 'orWhere';
                $query->{$method}('phone', 'like', '%'.$needle.'%');
            }
            $customer = $query->first();

            if ($customer) {
                $customer->updateQuietly(['last_contacted_at' => now()]);
                Log::info('OpenWA inbound: customer marked as contacted', [
                    'customer_id' => $customer->id,
                    'chatId' => $chatId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('OpenWA inbound: failed to mark customer as contacted', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
