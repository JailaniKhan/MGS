@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4 page-enter">
    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.cashbook') }}</h2>
    <a href="{{ route('cashbook.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.new_entry') }}</a>
</div>

@if($cashUnbalanced ?? false)
    <div class="card mb-4 border border-danger-500/40 bg-danger-500/5 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-start gap-2 p-3">
            <x-icon name="exclamation-triangle" class="w-4 h-4 text-danger-500 flex-shrink-0 mt-0.5" strokeWidth="1.8"/>
            <p class="text-xs font-medium text-danger-600 dark:text-danger-400 leading-relaxed">{{ __('messages.cashbook_unbalanced') }}</p>
        </div>
    </div>
@endif

<div class="grid grid-cols-2 gap-3 mb-4 page-enter" style="animation-delay: 0.05s;">
    <div class="metric-tile">
        <span class="metric-label">{{ __('messages.cash_afn') }}</span>
        <span class="metric-value text-primary-600 dark:text-primary-400">{{ number_format((float) $cashAFN, 2) }}</span>
    </div>
    <div class="metric-tile">
        <span class="metric-label">{{ __('messages.cash_usd') }}</span>
        <span class="metric-value text-primary-600 dark:text-primary-400">{{ number_format((float) $cashUSD, 2) }}</span>
    </div>
</div>

<h3 class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400 mb-2 page-enter">{{ __('messages.by_person') }}</h3>

<div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
    @forelse ($people as $person)
        @php
            $netAFN = $person['in']['AFN'] - $person['out']['AFN'];
            $netUSD = $person['in']['USD'] - $person['out']['USD'];
        @endphp
        <a href="{{ route('cashbook.person', [$person['type'], $person['id']]) }}" class="list-row group">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                    <span class="text-white font-bold text-sm">{{ mb_substr($person['name'], 0, 1) }}</span>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $person['name'] }}</div>
                    <div class="text-[11px] text-ink-500 dark:text-ink-400">
                        {{ $person['count'] }} {{ __('messages.entries') }}
                        <span class="capitalize">&middot; {{ $person['type'] }}</span>
                    </div>
                </div>
            </div>
            <div class="text-right flex-shrink-0 ml-3">
                @if($netAFN != 0)
                    <div class="text-sm font-bold {{ $netAFN >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $netAFN >= 0 ? '+' : '-' }}{{ number_format(abs($netAFN), 2) }} <span class="text-[10px] text-ink-400">AFN</span>
                    </div>
                @endif
                @if($netUSD != 0)
                    <div class="text-sm font-bold {{ $netUSD >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $netUSD >= 0 ? '+' : '-' }}{{ number_format(abs($netUSD), 2) }} <span class="text-[10px] text-ink-400">USD</span>
                    </div>
                @endif
                @if($netAFN == 0 && $netUSD == 0)
                    <div class="text-sm font-bold text-ink-400">—</div>
                @endif
            </div>
        </a>
    @empty
        <div class="empty-state">
            <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                <x-icon name="currency-dollar" class="w-6 h-6 text-ink-400"/>
            </div>
            <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_entries') }}</p>
        </div>
    @endforelse
</div>
@if ($people->hasPages())
    <div class="mt-3 mb-4">{{ $people->links() }}</div>
@endif

@if($uncategorized->isNotEmpty())
    <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400 mt-4 mb-2 page-enter">{{ __('messages.uncategorized') }}</h3>
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        @foreach ($uncategorized as $journal)
            <div class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl {{ $journal->type === 'in' ? 'bg-primary-500 text-white' : 'bg-danger-500 text-white' }} flex items-center justify-center flex-shrink-0 shadow-sm">
                        @if($journal->type === 'in')
                        <x-icon name="plus" class="w-4 h-4 text-white"/>
                        @else
                        <x-icon name="minus" class="w-4 h-4 text-white"/>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $journal->description }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">
                            {{ $journal->transaction_date->format('d M Y') }}
                        </div>
                        @if($journal->notes)
                            <div class="text-[10px] text-ink-400 mt-0.5">{{ $journal->notes }}</div>
                        @endif
                    </div>
                </div>
                <div class="text-right flex-shrink-0 ml-3">
                    <div class="text-sm font-bold {{ $journal->type === 'in' ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $journal->type === 'in' ? '+' : '-' }}{{ number_format((float) $journal->total_amount, 2) }}
                    </div>
                    <div class="text-[10px] text-ink-400">{{ $journal->currency }}</div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
