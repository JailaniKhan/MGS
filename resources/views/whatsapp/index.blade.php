@extends('layouts.app')

@section('content')
<div class="page-enter">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.whatsapp_chats') }}</h2>
        <a href="{{ route('settings.openwa') }}" class="text-sm text-primary-600 dark:text-primary-400">{{ __('messages.whatsapp_gateway') }}</a>
    </div>

    <div class="card overflow-hidden">
        @forelse ($chats as $chat)
            <a href="{{ route('whatsapp.chats.show', [$chat['type'], $chat['id']]) }}"
               class="list-row hover:bg-ink-50 dark:hover:bg-white/[0.03]">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold text-white {{ $chat['avatar_class'] }}">
                        {{ $chat['initials'] }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-ink-900 dark:text-ink-100 truncate">{{ $chat['name'] }}</span>
                            <span class="text-[10px] text-ink-400 flex-shrink-0">{{ $chat['time_label'] }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-0.5 min-w-0">
                            <span class="badge text-[10px] px-1.5 py-0.5 flex-shrink-0
                                @if($chat['last_status'] === 'sent') badge-success
                                @elseif($chat['last_status'] === 'failed') badge-danger
                                @else badge-warning @endif">
                                {{ __("messages.wa_{$chat['last_status']}") }}
                            </span>
                            <p class="text-xs text-ink-500 dark:text-ink-400 truncate">{{ $chat['last_message'] }}</p>
                        </div>
                        @if ($chat['phone'])
                            <div class="text-[10px] text-ink-400 mt-0.5">{{ $chat['phone'] }}</div>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="empty-state">
                <div class="empty-illustration">
                    <x-icon name="chat-bubble-left-right" class="w-6 h-6 text-ink-400"/>
                </div>
                <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_whatsapp_chats') }}</p>
                <p class="text-xs text-ink-400 mt-1">{{ __('messages.no_whatsapp_chats_hint') }}</p>
            </div>
        @endforelse
    </div>

    @if ($chats->hasPages())
        <div class="mt-4">{{ $chats->links() }}</div>
    @endif
</div>
@endsection