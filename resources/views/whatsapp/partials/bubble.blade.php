@php
    $isVoice = !empty($reminder->media_path) && str_starts_with((string) $reminder->media_type, 'audio/');
    $isImage = !empty($reminder->media_path) && str_starts_with((string) $reminder->media_type, 'image/');
    $status = $reminder->status;
    // Incoming replies (folded in by InboundMessageService) align left with
    // WhatsApp's white bubble and carry no delivery ticks.
    $isIncoming = ($reminder->direction ?? 'out') === 'in' || $status === 'received';
    $bubbleTone = $isIncoming
        ? 'bg-white dark:bg-[#1e2127] text-ink-900 dark:text-[#e9edef]'
        : match ($status) {
            'sent' => 'bg-[#d9fdd3] dark:bg-[#005c4b] text-ink-900 dark:text-[#e9edef]',
            'failed' => 'bg-danger-100 dark:bg-danger-900/50 text-danger-800 dark:text-danger-200',
            default => 'bg-white dark:bg-[#1e2127] text-ink-900 dark:text-ink-100',
        };
    $tailTone = $isIncoming
        ? 'bg-white dark:bg-[#1e2127]'
        : ($status === 'failed'
            ? 'bg-danger-100 dark:bg-danger-900/50'
            : 'bg-[#d9fdd3] dark:bg-[#005c4b]');
@endphp
@if ($loop->first || !($messages[$loop->index - 1]->created_at->isSameDay($reminder->created_at)))
    <div class="flex justify-center py-2.5">
        <span class="text-[10px] font-bold text-ink-600 dark:text-ink-300 bg-white/85 dark:bg-[#1f282e]/95 backdrop-blur px-3 py-1 rounded-full shadow-sm tracking-wide border border-black/[0.04] dark:border-white/[0.06]">
            {{ $dateLabel($reminder->created_at) }}
        </span>
    </div>
@endif
<div class="flex {{ $isIncoming ? 'justify-start' : 'justify-end' }} page-enter">
    <div class="wa-bubble relative max-w-[85%] px-3 py-2 shadow-sm break-words {{ $bubbleTone }}">
        <span class="wa-tail absolute -me-2 bottom-1.5 w-2 h-3 {{ $tailTone }}" aria-hidden="true"></span>
        @if ($isImage)
            <a href="{{ route('whatsapp.chats.media', $reminder) }}" target="_blank" rel="noopener"
               class="block rounded-xl overflow-hidden" aria-label="{{ __('messages.wa_photo') }}">
                <img src="{{ route('whatsapp.chats.media', $reminder) }}" alt="{{ __('messages.wa_photo') }}" class="wa-img">
            </a>
            @if ($reminder->message)
                <div class="leading-snug whitespace-pre-line wa-text mt-1.5 px-1" dir="auto">{{ $reminder->message }}</div>
            @endif
        @elseif ($isVoice)
            <div class="flex items-center gap-2 py-0.5">
                <button type="button" class="wa-play w-9 h-9 rounded-full bg-brand text-white grid place-items-center active:scale-95 transition-transform flex-shrink-0 shadow-sm"
                        data-src="{{ route('whatsapp.chats.media', $reminder) }}"
                        aria-label="{{ __('messages.wa_voice_note') }}">
                    <svg class="wa-play-icon w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5.14v13.72c0 .96 1.05 1.55 1.87 1.05l10.29-6.86a1.25 1.25 0 000-2.1L9.87 4.09C9.05 3.59 8 4.18 8 5.14z"/></svg>
                </button>
                <div class="flex items-end gap-[3px] h-6" aria-hidden="true">
                    @foreach ([10, 18, 12, 22, 15, 20, 11] as $h)
                        <span class="wa-eq w-[3px] rounded-full bg-black/25 dark:bg-white/25" style="height: {{ $h }}px; animation-delay: {{ ($loop->index * 120) }}ms"></span>
                    @endforeach
                </div>
                <span class="text-[10px] opacity-70 tabular-nums wa-duration" dir="ltr">--:--</span>
            </div>
        @else
            <div class="leading-snug whitespace-pre-line wa-text" dir="auto">{{ $reminder->message }}</div>
        @endif

        @if ($status === 'failed' && $reminder->error_message)
            <div class="text-[10px] mt-1.5 opacity-80">{{ $reminder->error_message }}</div>
        @endif

        <div class="flex items-center justify-end gap-1 mt-1">
            <span class="text-[10px] opacity-70 tabular-nums" dir="ltr">
                {{ ($reminder->sent_at ?? $reminder->created_at)->format('H:i') }}
            </span>
            @if (! $isIncoming)
                <x-icon name="{{ $status === 'sent' ? 'check' : ($status === 'failed' ? 'x-mark' : 'clock') }}"
                        class="w-3.5 h-3.5 {{ $status === 'sent' ? 'text-[#53bdeb]' : ($status === 'failed' ? 'text-danger-500' : 'text-accent-600') }}"
                        strokeWidth="{{ $status === 'failed' ? 2.5 : 2 }}"/>
            @endif
        </div>
    </div>
</div>
