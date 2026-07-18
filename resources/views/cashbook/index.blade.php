@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4 page-enter">
    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.cashbook') }}</h2>
    <a href="{{ route('cashbook.create') }}" class="btn-primary btn-sm"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>{{ __('messages.new_entry') }}</a>
</div>

@if($cashUnbalanced ?? false)
    <div class="card mb-4 border border-danger-500/40 bg-danger-500/5 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-start gap-2 p-3">
            <svg class="w-4 h-4 text-danger-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.34 3.94l-7.06 12.22A1.5 1.5 0 004.59 18.3h14.82a1.5 1.5 0 001.31-2.14L13.66 3.94a1.5 1.5 0 00-2.61 0z"/>
            </svg>
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
                <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_entries') }}</p>
        </div>
    @endforelse
</div>

@if($uncategorized->isNotEmpty())
    <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400 mt-4 mb-2 page-enter">{{ __('messages.uncategorized') }}</h3>
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        @foreach ($uncategorized as $journal)
            <div class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl {{ $journal->type === 'in' ? 'bg-primary-500 text-white' : 'bg-danger-500 text-white' }} flex items-center justify-center flex-shrink-0 shadow-sm">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            @if($journal->type === 'in')
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                            @else
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15"/>
                            @endif
                        </svg>
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
