@extends('layouts.app')

@section('content')
    <div class="page-enter">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.reminder_history') }}</h2>
        </div>

        <div class="card overflow-hidden">
            @forelse ($reminders as $reminder)
                <div class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                            @if($reminder->status === 'sent') bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400
                            @elseif($reminder->status === 'failed') bg-danger-100 dark:bg-danger-900/30 text-danger-600 dark:text-danger-400
                            @else bg-accent-100 dark:bg-accent-900/30 text-accent-600 dark:text-accent-400 @endif">
                            @if($reminder->status === 'sent')
                                <x-icon name="check-circle" class="w-4 h-4"/>
                            @elseif($reminder->status === 'failed')
                                <x-icon name="x-mark" class="w-4 h-4"/>
                            @else
                                <x-icon name="clock" class="w-4 h-4"/>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-ink-900 dark:text-ink-100 truncate">
                                {{ $reminder->remindable?->name ?? __('messages.deleted') }}
                                <span class="badge {{ $reminder->channel === 'whatsapp' ? 'badge-success' : 'badge-info' }} text-[10px] px-1.5 py-0.5">
                                    {{ $reminder->channel === 'whatsapp' ? 'WhatsApp' : 'SMS' }}
                                </span>
                            </div>
                            <div class="text-xs text-ink-500 dark:text-ink-400 mt-0.5 truncate">{{ $reminder->message }}</div>
                            <div class="text-[10px] text-ink-400 mt-0.5"><bdi>{{ local_date($reminder->created_at, 'Y/m/d H:i') }}</bdi></div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <span class="badge
                            @if($reminder->status === 'sent') badge-success
                            @elseif($reminder->status === 'failed') badge-danger
                            @else badge-warning @endif">
                            {{ __("messages.{$reminder->status}") }}
                        </span>
                    </div>
                </div>
            @empty
                <x-empty-state title="{{ __('messages.no_reminders') }}">
                    <x-icon name="bell" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>

        @if ($reminders->hasPages())
            <div class="mt-4">{{ $reminders->links() }}</div>
        @endif
    </div>
@endsection
