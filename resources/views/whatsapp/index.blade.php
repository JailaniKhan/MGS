@extends('layouts.app')

@section('content')
@php
    $failedCount = collect($chats->items())->where('last_status', 'failed')->count();
@endphp

<div class="pb-16">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-primary-500/10 dark:bg-primary-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="chat-bubble-left-right" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.whatsapp_chats') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">
                    {{ $chats->total() }} {{ __('messages.chats') ?? 'Chats' }}
                    @if ($failedCount > 0)
                        &middot; <span class="text-danger-500">{{ $failedCount }} {{ __('messages.wa_failed') }}</span>
                    @endif
                </p>
            </div>
        </div>
        <a href="{{ route('settings.openwa') }}" class="btn-ghost btn-sm flex-shrink-0">
            <x-icon name="signal" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.whatsapp_gateway') }}
        </a>
    </div>

    {{-- Search --}}
    <div class="search-bar sticky top-[3.5rem] z-10 mb-3 page-enter" style="animation-delay: 0.05s;">
        <x-icon name="magnifying-glass" class="search-icon" strokeWidth="1.8"/>
        <input type="search" inputmode="search"
               data-list-filter="wa-chat-list"
               data-empty-text="{{ __('messages.no_results') }}"
               autocomplete="off"
               placeholder="{{ __('messages.search') }}"
               class="flex-1">
    </div>

    {{-- Chat list --}}
    <div class="card overflow-hidden page-enter" id="wa-chat-list" style="animation-delay: 0.08s;">
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($chats as $chat)
                <a href="{{ route('whatsapp.chats.show', [$chat['type'], $chat['id']]) }}"
                   class="list-row block active:bg-ink-50 dark:active:bg-white/[0.03] transition-colors">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="relative flex-shrink-0">
                            <div class="w-11 h-11 rounded-full flex items-center justify-center text-sm font-bold text-white {{ $chat['avatar_class'] }}">
                                {{ $chat['initials'] }}
                            </div>
                            @if ($chat['last_status'] === 'failed')
                                <span class="absolute -end-0.5 -top-0.5 w-3.5 h-3.5 rounded-full bg-danger-500 border-2 border-white dark:border-[#1a1b1d]"></span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $chat['name'] }}</span>
                                <span class="text-[10px] font-medium text-ink-400 flex-shrink-0 tabular-nums">{{ $chat['time_label'] }}</span>
                            </div>
                            <p class="text-xs text-ink-500 dark:text-ink-400 truncate mt-0.5">
                                @if (!empty($chat['is_voice']))
                                    <span class="inline-flex items-center gap-1 align-middle"><x-icon name="microphone" class="w-3 h-3 inline" strokeWidth="2"/>{{ __('messages.wa_voice_note') }}</span>
                                @else
                                    {{ $chat['last_message'] }}
                                @endif
                            </p>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold px-1.5 py-0.5 rounded-full border tabular-nums
                                    @if($chat['last_status'] === 'sent') bg-primary-500/10 text-primary-600 dark:text-primary-400 border-primary-500/20
                                    @elseif($chat['last_status'] === 'failed') bg-danger-50 dark:bg-danger-900/30 text-danger-600 dark:text-danger-400 border-danger-200 dark:border-danger-700/50
                                    @else bg-accent-500/10 text-accent-600 dark:text-accent-400 border-accent-500/20 @endif">
                                    <x-icon name="{{ $chat['last_status'] === 'sent' ? 'check' : ($chat['last_status'] === 'failed' ? 'x-mark' : 'clock') }}" class="w-2.5 h-2.5" strokeWidth="2.5"/>
                                    {{ __('messages.wa_' . (in_array($chat['last_status'], ['sent', 'failed', 'pending', 'drafted'], true) ? $chat['last_status'] : 'pending')) }}
                                </span>
                                @if ($chat['phone'])
                                    <span class="text-[10px] text-ink-400 truncate" dir="ltr">{{ $chat['phone'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <x-empty-state title="{{ __('messages.no_whatsapp_chats') }}" :description="__('messages.wa_empty_conversation_hint')">
                    <x-icon name="chat-bubble-left-right" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
    </div>

    @if ($chats->hasPages())
        <div class="mt-3">{{ $chats->links() }}</div>
    @endif
</div>

{{-- New chat FAB --}}
<button type="button" id="wa-new-chat"
        class="fixed end-4 z-40 w-14 h-14 rounded-2xl bg-brand text-white shadow-nav dark:shadow-nav-dark grid place-items-center
               hover:brightness-105 active:scale-95 transition-all page-enter"
        style="bottom: calc(5.6rem + env(safe-area-inset-bottom, 0px)); animation-delay: 0.12s;"
        aria-label="{{ __('messages.wa_new_chat') }}" aria-haspopup="dialog">
    <x-icon name="plus" class="w-6 h-6" strokeWidth="2.2"/>
</button>

{{-- Contact picker sheet --}}
<div id="wa-picker-backdrop" class="sheet-backdrop" aria-hidden="true"></div>
<div id="wa-picker" class="bottom-sheet" role="dialog" aria-modal="true" aria-label="{{ __('messages.wa_new_chat') }}">
    <div class="sheet-handle"></div>
    <div class="px-4 pb-5 pt-1">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-bold text-ink-900 dark:text-white">{{ __('messages.wa_new_chat') }}</h3>
            <button type="button" id="wa-picker-close" class="w-8 h-8 rounded-xl grid place-items-center text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 hover:bg-ink-100 dark:hover:bg-white/[0.06] transition-colors">
                <x-icon name="x-mark" class="w-5 h-5" strokeWidth="2"/>
            </button>
        </div>

        <div class="search-bar mb-3 !sticky !top-0 !z-10">
            <x-icon name="magnifying-glass" class="search-icon" strokeWidth="1.8"/>
            <input type="search" id="wa-picker-search" autocomplete="off"
                   placeholder="{{ __('messages.search') }}" class="flex-1">
        </div>

        <div id="wa-picker-list" class="max-h-[52vh] overflow-y-auto overscroll-contain divide-y divide-ink-100 dark:divide-ink-700/30 -mx-1 px-1">
            @forelse ($contacts as $c)
                <a href="{{ route('whatsapp.chats.show', [$c['type'], $c['id']]) }}"
                   class="wa-picker-row flex items-center gap-3 py-2.5 px-1 rounded-xl hover:bg-ink-50 dark:hover:bg-white/[0.04] active:bg-ink-100 dark:active:bg-white/[0.07] transition-colors"
                   data-filter="{{ mb_strtolower($c['name'] . ' ' . $c['phone']) }}">
                    <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold text-white
                        {{ ['bg-primary-500', 'bg-accent-500', 'bg-emerald-500', 'bg-sky-500', 'bg-amber-500', 'bg-rose-500'][crc32($c['type'] . ':' . $c['id']) % 6] }}">
                        {{ mb_strtoupper(mb_substr($c['name'], 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1 leading-tight">
                        <p class="text-sm font-semibold text-ink-800 dark:text-ink-200 truncate">{{ $c['name'] }}</p>
                        <p class="text-[11px] text-ink-400 truncate" dir="ltr">{{ $c['phone'] }}</p>
                    </div>
                    <span class="text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-md flex-shrink-0
                        {{ $c['type'] === 'customer' ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400' : 'bg-secondary-500/10 text-secondary-600 dark:text-secondary-400' }}">
                        {{ $c['type'] === 'customer' ? __('messages.customer') : __('messages.supplier') }}
                    </span>
                </a>
            @empty
                <p class="text-center text-xs text-ink-400 py-8">{{ __('messages.wa_no_contacts') }}</p>
            @endforelse
        </div>
    </div>
</div>

<script>
(function () {
    var fab = document.getElementById('wa-new-chat');
    var backdrop = document.getElementById('wa-picker-backdrop');
    var sheet = document.getElementById('wa-picker');
    var closeBtn = document.getElementById('wa-picker-close');
    var search = document.getElementById('wa-picker-search');
    if (!fab) return;

    function openPicker() {
        backdrop.classList.add('open');
        sheet.classList.add('open');
        setTimeout(function () { search && search.focus(); }, 180);
    }
    function closePicker() {
        backdrop.classList.remove('open');
        sheet.classList.remove('open');
        search.value = '';
        filterRows('');
    }
    function filterRows(q) {
        q = q.trim().toLowerCase();
        document.querySelectorAll('.wa-picker-row').forEach(function (row) {
            var hay = row.dataset.filter || '';
            row.style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
        });
    }

    fab.addEventListener('click', openPicker);
    closeBtn.addEventListener('click', closePicker);
    backdrop.addEventListener('click', closePicker);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sheet.classList.contains('open')) closePicker();
    });
    search && search.addEventListener('input', function () { filterRows(search.value); });
})();
</script>
@endsection
