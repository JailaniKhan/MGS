@extends('layouts.app')

@section('content')
    <div class="page-header page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.ledger') }}</h2>
    </div>

    <div class="card overflow-hidden">
        @forelse ($people as $person)
            <a href="{{ route('ledger.show', [$person['type'], $person['id']]) }}" class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-ink-800 dark:text-ink-200">{{ $person['name'] }}</span>
                            <span class="badge {{ $person['type'] === 'customer' ? 'badge-info' : 'badge-warning' }}">{{ $person['type_label'] }}</span>
                        </div>
                        @if ($person['phone'])
                            <div class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">{{ $person['phone'] }}</div>
                        @endif
                    </div>
                </div>
                <div class="text-right flex-shrink-0 ml-3">
                    @if ($person['remaining_afn'] > 0)
                        <div class="text-sm font-semibold {{ $person['type'] === 'customer' ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">{{ number_format($person['remaining_afn']) }} {{ __('messages.afn') }}</div>
                    @endif
                    @if ($person['remaining_usd'] > 0)
                        <div class="text-sm font-semibold {{ $person['type'] === 'customer' ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">{{ number_format($person['remaining_usd']) }}$</div>
                    @endif
                    @if ($person['remaining_afn'] <= 0 && $person['remaining_usd'] <= 0)
                        <span class="badge badge-success">{{ __('messages.fully_paid') }}</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="empty-state">
                <p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_customers_or_suppliers') }}</p>
            </div>
        @endforelse
    </div>
    @if ($people->hasPages())
        <div class="mt-4">{{ $people->links() }}</div>
    @endif
@endsection