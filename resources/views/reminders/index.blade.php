@extends('layouts.app')

@section('content')
    <div class="page-enter">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.reminder_history') }}</h2>
        </div>

        <div class="card overflow-hidden">
            @forelse ($reminders as $reminder)
                <div class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                            @if($reminder->status === 'sent') bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400
                            @elseif($reminder->status === 'failed') bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400
                            @else bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 @endif">
                            @if($reminder->status === 'sent')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($reminder->status === 'failed')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ $reminder->remindable?->name ?? __('messages.deleted') }}
                                <span class="badge {{ $reminder->channel === 'whatsapp' ? 'badge-success' : 'badge-info' }} text-[10px] px-1.5 py-0.5">
                                    {{ $reminder->channel === 'whatsapp' ? 'WhatsApp' : 'SMS' }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $reminder->message }}</div>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ $reminder->created_at->format('Y/m/d H:i') }}</div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <span class="badge
                            @if($reminder->status === 'sent') badge-success
                            @elseif($reminder->status === 'failed') badge-danger
                            @else badge-warning @endif">
                            {{ __("messages.{$reminder->status}") }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('messages.no_reminders') }}</p>
                </div>
            @endforelse
        </div>

        @if ($reminders->hasPages())
            <div class="mt-4">{{ $reminders->links() }}</div>
        @endif
    </div>
@endsection
