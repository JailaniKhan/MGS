<?php

namespace App\Services\WhatsApp;

use App\Models\Customer;
use App\Models\Reminder;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Folds inbound WhatsApp messages (from the gateway's polling endpoint or
 * the signed webhook) into the reminders table so replies render as
 * incoming bubbles in the WhatsApp-style chat pages.
 *
 * Deduplication happens on provider_message_id — the poller and the webhook
 * can both see the same WhatsApp message, and the poller replays its cursor
 * after a gateway restart.
 */
class InboundMessageService
{
    protected const CURSOR_KEY = 'openwa:inbound:cursor';

    /**
     * Poll the gateway and persist everything new. Called by the
     * whatsapp:sync-inbound command (scheduled every minute).
     *
     * @return int number of messages stored
     */
    public function sync(): int
    {
        $since = $this->cursor();

        $result = app(OpenWaService::class)->messages($since);
        if ($result === null) {
            return 0;
        }

        $stored = 0;
        foreach ($result['messages'] as $entry) {
            if ($this->store($entry)) {
                $stored++;
            }
        }

        // A gateway restart resets its sequence to 0; trust the highest
        // cursor either side has ever reported so we never rewind and
        // re-store the whole buffer.
        $this->cacheCursor(max($this->cursor(), (int) $result['cursor'], $since));

        return $stored;
    }

    public function cursor(): int
    {
        return (int) Cache::get(self::CURSOR_KEY, 0);
    }

    public function cacheCursor(int $cursor): void
    {
        Cache::forever(self::CURSOR_KEY, $cursor);
    }

    /**
     * Persist one inbound message. Returns true when a new reminder row was
     * created (false = duplicate or unmatchable chat).
     *
     * Expected shape (from the gateway poller or the webhook payload):
     *   ['id' => string|null, 'chatId' => string, 'text' => string,
     *    'timestamp' => int ms, 'mediaType' => string|null]
     */
    public function store(array $entry): bool
    {
        $chatId = (string) ($entry['chatId'] ?? ($entry['from'] ?? ''));
        $text = trim((string) ($entry['text'] ?? ''));
        $mediaType = $entry['mediaType'] ?? null;
        $providerId = $entry['id'] ?? null;

        if ($chatId === '' || ($text === '' && $mediaType === null)) {
            return false;
        }

        $phone = OpenWaService::chatIdToPhone($chatId);
        if (! $phone) {
            return false;
        }

        $contact = $this->findContact($phone);
        if (! $contact) {
            Log::info('OpenWA inbound: no matching customer/supplier', ['chatId' => $chatId]);

            return false;
        }

        // Idempotency: the poller and the webhook both deliver the same WA
        // message; provider_message_id is unique in practice.
        if ($providerId && Reminder::where('provider_message_id', $providerId)->exists()) {
            return false;
        }

        $contactClass = $contact instanceof Customer ? 'customer' : 'supplier';

        Reminder::create([
            // The poller runs in console (no Auth user), so ownership comes
            // from the contact — BelongsToUser's global scope then keeps the
            // row inside the right shop's chats.
            'user_id' => $contact->user_id,
            'remindable_type' => $contactClass,
            'remindable_id' => $contact->id,
            'channel' => 'whatsapp',
            'message' => $text !== '' ? $text : '['.__('messages.wa_media_message').']',
            'status' => 'received',
            'direction' => 'in',
            'provider_message_id' => $providerId,
            'sent_at' => isset($entry['timestamp'])
                ? Carbon::createFromTimestampMs((int) $entry['timestamp'])
                : now(),
        ]);

        if ($contact instanceof Customer) {
            // Inbound beats every reminder loop: stop nagging them today.
            $contact->updateQuietly(['last_contacted_at' => now()]);
        }

        return true;
    }

    /**
     * Match a phone (digits only, with or without the 93 country code) to a
     * customer or supplier. Rows store phones in inconsistent formats
     * (+9370... / 070... / spaced), so several needles are tried LIKE-style.
     *
     * @return Customer|Supplier|null
     */
    public function findContact(string $phone)
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        $local = str_starts_with($digits, '93') ? '0'.substr($digits, 2) : $digits;

        $needles = array_values(array_unique(array_filter([
            $phone,
            $digits,
            $local,
            '+'.$digits,
        ])));

        // BelongsToUser scopes both models to the (console-absent) auth user;
        // queries must ignore that scope and match across every shop account.
        foreach ([Customer::class, Supplier::class] as $class) {
            $contact = $class::query()
                ->withoutGlobalScopes()
                ->where(function ($q) use ($needles) {
                    foreach ($needles as $needle) {
                        $q->orWhere('phone', 'like', '%'.$needle.'%');
                    }
                })
                // Shortest phone = least padded format = tightest match.
                ->orderByRaw('LENGTH(phone)')
                ->first();
            if ($contact) {
                return $contact;
            }
        }

        return null;
    }
}
