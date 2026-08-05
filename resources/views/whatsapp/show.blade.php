@extends('layouts.app')

@section('content')
@php
    $name = $contact?->name ?? __('messages.deleted');
    $initials = mb_strtoupper(mb_substr($name, 0, 1));
    $colors = ['bg-primary-500', 'bg-accent-500', 'bg-emerald-500', 'bg-sky-500', 'bg-amber-500', 'bg-rose-500', 'bg-violet-500'];
    $avatarClass = $colors[crc32($type . ':' . $id) % count($colors)];
    $bubbleClass = fn ($status) => match ($status) {
        'sent' => 'bg-[#d9fdd3] dark:bg-emerald-900/50 text-ink-900 dark:text-ink-100',
        'failed' => 'bg-danger-100 dark:bg-danger-900/50 text-danger-800 dark:text-danger-200',
        default => 'bg-amber-100 dark:bg-amber-900/50 text-ink-900 dark:text-ink-100',
    };
@endphp
<div class="page-enter">
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('whatsapp.chats.index') }}" class="text-sm text-primary-600 dark:text-primary-400">&larr; {{ __('messages.whatsapp_chats') }}</a>
    </div>

    <div class="card overflow-hidden">
        {{-- Contact header --}}
        <div class="px-4 py-3 border-b border-ink-100 dark:border-white/[0.06] flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold text-white {{ $avatarClass }}">
                {{ $initials }}
            </div>
            <div class="min-w-0">
                <div class="text-sm font-semibold text-ink-900 dark:text-ink-100 truncate">{{ $name }}</div>
                <div class="text-xs text-ink-500 dark:text-ink-400 truncate">{{ $contact?->phone ?? '' }}</div>
            </div>
            <span class="badge badge-success ml-auto flex-shrink-0">{{ __('messages.wa_channel') }}</span>
        </div>

        {{-- Conversation: outgoing WhatsApp-style bubbles --}}
        <div class="px-4 py-4 space-y-2.5 bg-[#efeae2] dark:bg-ink-900/40">
            @foreach ($messages as $reminder)
                <div class="flex justify-end">
                    <div class="max-w-[82%] rounded-2xl rounded-tr-sm px-3 py-2 text-sm shadow-sm break-words {{ $bubbleClass($reminder->status) }}">
                        <div class="leading-snug">{{ $reminder->message }}</div>
                        <div class="flex items-center justify-end gap-1 mt-1">
                            <span class="text-[10px] opacity-70">
                                {{ ($reminder->sent_at ?? $reminder->created_at)?->format('H:i') }}
                            </span>
                            <x-icon name="{{ $reminder->status === 'sent' ? 'check' : ($reminder->status === 'failed' ? 'x-mark' : 'clock') }}"
                                    class="w-3.5 h-3.5 {{ $reminder->status === 'sent' ? 'text-emerald-600' : ($reminder->status === 'failed' ? 'text-danger-500' : 'text-amber-600') }}"/>
                        </div>
                        @if ($reminder->status === 'failed' && $reminder->error_message)
                            <div class="text-[10px] mt-1 opacity-80">{{ $reminder->error_message }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection