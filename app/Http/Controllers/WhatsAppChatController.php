<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Reminder;
use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * WhatsApp-style view of the reminders that were actually delivered (or
 * attempted) over WhatsApp. Outgoing messages only — inbound messages are
 * not persisted by the gateway (the webhook only stamps last_contacted_at).
 */
class WhatsAppChatController extends Controller
{
    protected const CHAT_TYPES = ['customer', 'supplier'];

    protected const AVATAR_COLORS = [
        'bg-primary-500', 'bg-accent-500', 'bg-emerald-500',
        'bg-sky-500', 'bg-amber-500', 'bg-rose-500', 'bg-violet-500',
    ];

    /**
     * Chat list: the latest WhatsApp reminder per contact, newest first —
     * mimics WhatsApp's conversation list (avatar, name, preview, time).
     */
    public function index()
    {
        $reminders = Reminder::query()
            ->where('channel', 'whatsapp')
            ->with('remindable')
            ->orderByDesc('created_at')
            ->get();

        // Group by the polymorphic contact (customer/supplier). The phone is
        // not stored on the reminder row, so the contact is the grouping key.
        $groups = $reminders->groupBy(fn (Reminder $r) => $r->remindable_type.':'.$r->remindable_id);

        $chats = $groups
            ->map(function ($items, string $key) {
                /** @var Reminder $latest */
                $latest = $items->first();
                $contact = $latest->remindable;
                $name = $contact?->name ?: __('messages.deleted');
                $time = $latest->sent_at ?? $latest->created_at;

                return [
                    'key' => $key,
                    'type' => $latest->remindable_type,
                    'id' => $latest->remindable_id,
                    'name' => $name,
                    'phone' => $contact?->phone ?? null,
                    'last_message' => $latest->message,
                    // Voice sends store empty text — preview a mic label instead.
                    'is_voice' => (bool) $latest->media_path && str_starts_with((string) $latest->media_type, 'audio/'),
                    'is_image' => (bool) $latest->media_path && str_starts_with((string) $latest->media_type, 'image/'),
                    'last_status' => $latest->status,
                    'last_time' => $time,
                    // WhatsApp-style row: initials avatar, stable color per
                    // contact, relative-ish timestamp.
                    'initials' => mb_strtoupper(mb_substr($name, 0, 1)),
                    'avatar_class' => self::AVATAR_COLORS[crc32($key) % count(self::AVATAR_COLORS)],
                    'time_label' => $time->isToday()
                        ? $time->format('H:i')
                        : ($time->isYesterday() ? __('messages.yesterday') : $time->format('d/m/Y')),
                ];
            })
            ->sortByDesc('last_time')
            ->values();

        $chats = $this->paginateCollection($chats);

        // Contacts for the "new chat" picker: everyone reachable on
        // WhatsApp, whether or not they have prior history.
        $contacts = Customer::query()
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn ($c) => ['type' => 'customer', 'id' => $c->id, 'name' => $c->name, 'phone' => $c->phone])
            ->merge(
                Supplier::query()
                    ->whereNotNull('phone')->where('phone', '!=', '')
                    ->orderBy('name')
                    ->get(['id', 'name', 'phone'])
                    ->map(fn ($s) => ['type' => 'supplier', 'id' => $s->id, 'name' => $s->name, 'phone' => $s->phone])
            )
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('whatsapp.index', compact('chats', 'contacts'));
    }

    /**
     * Conversation: every WhatsApp reminder sent to one contact, oldest
     * first, rendered as outgoing WhatsApp-style bubbles. Contacts with no
     * history render an empty conversation — that's the entry point for
     * starting a brand-new chat from the picker.
     */
    public function show(string $type, int $id)
    {
        if (! in_array($type, self::CHAT_TYPES, true)) {
            abort(404);
        }

        $contactClass = $type === 'customer' ? Customer::class : Supplier::class;
        $contact = $contactClass::find($id);

        // A chat must always have a live contact behind it (deleted
        // contacts' history stays visible in the list but can't be opened).
        if (! $contact) {
            abort(404);
        }

        $messages = Reminder::query()
            ->where('channel', 'whatsapp')
            ->where('remindable_type', $type)
            ->where('remindable_id', $id)
            ->orderBy('created_at')
            ->get();

        return view('whatsapp.show', compact('messages', 'type', 'id', 'contact'));
    }

    /**
     * Poll endpoint for the chat page: everything newer than the given
     * reminder id, rendered as the same bubble partial the page uses so the
     * JS poller can just insert the fragment. Incoming messages are folded
     * into the reminders table by InboundMessageService; without this poll
     * they would only appear after a full page reload.
     */
    public function messages(string $type, int $id, Request $request)
    {
        if (! in_array($type, self::CHAT_TYPES, true)) {
            abort(404);
        }

        $contactClass = $type === 'customer' ? Customer::class : Supplier::class;
        if (! $contactClass::find($id)) {
            abort(404);
        }

        $after = (int) $request->query('after', 0);

        $newer = Reminder::query()
            ->where('channel', 'whatsapp')
            ->where('remindable_type', $type)
            ->where('remindable_id', $id)
            ->where('id', '>', $after)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $dateLabel = fn ($time) => $time->isToday()
            ? __('messages.today')
            : ($time->isYesterday() ? __('messages.yesterday') : $time->format('d/m/Y'));

        $html = $newer->map(fn ($reminder) => view('whatsapp.partials.bubble', [
            'reminder' => $reminder,
            'dateLabel' => $dateLabel,
            // The partial's day-chip logic reads the previous bubble out of
            // this collection; a single-item collection means "always chip",
            // which is correct for the first message of a poll batch.
            'messages' => $newer,
        ])->render())->implode('');

        return response()->json([
            'html' => $html,
            'lastId' => (int) ($newer->last()->id ?? $after),
        ]);
    }
}
