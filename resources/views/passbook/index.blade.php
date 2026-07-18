@extends('layouts.app')

@section('content')
<div class="page-enter">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.passbook') }}</h2>
    </div>

    <!-- Balance Summary -->
    <div class="grid grid-cols-2 gap-2 mb-4">
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_in') }}</span>
            <span class="metric-value text-primary-600 dark:text-primary-400">{{ number_format($totalIn) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.afn') }}</span>
            @if($totalInUSD > 0)
                <span class="text-[10px] text-primary-500">+ {{ number_format($totalInUSD) }} USD</span>
            @endif
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_out') }}</span>
            <span class="metric-value text-danger-500">{{ number_format($totalOut) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.afn') }}</span>
            @if($totalOutUSD > 0)
                <span class="text-[10px] text-danger-400">+ {{ number_format($totalOutUSD) }} USD</span>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <div class="card p-3 mb-4">
        <form method="GET" action="{{ route('passbook.index') }}" class="space-y-3">
            <div class="flex gap-2 overflow-x-auto pb-1">
                <a href="{{ route('passbook.index', ['filter' => 'all']) }}"
                   class="badge whitespace-nowrap {{ $filter === 'all' ? 'badge-success' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
                    {{ __('messages.all') }}
                </a>
                <a href="{{ route('passbook.index', ['filter' => 'cash_in']) }}"
                   class="badge whitespace-nowrap {{ $filter === 'cash_in' ? 'badge-success' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
                    {{ __('messages.cash_in') }}
                </a>
                <a href="{{ route('passbook.index', ['filter' => 'cash_out']) }}"
                   class="badge whitespace-nowrap {{ $filter === 'cash_out' ? 'badge-danger' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
                    {{ __('messages.cash_out') }}
                </a>
                <a href="{{ route('passbook.index', ['filter' => 'credit_in']) }}"
                   class="badge whitespace-nowrap {{ $filter === 'credit_in' ? 'badge-info' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
                    {{ __('messages.credit_in') }}
                </a>
                <a href="{{ route('passbook.index', ['filter' => 'credit_out']) }}"
                   class="badge whitespace-nowrap {{ $filter === 'credit_out' ? 'badge-warning' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
                    {{ __('messages.credit_out') }}
                </a>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input text-xs" placeholder="{{ __('messages.start_date') }}">
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input text-xs" placeholder="{{ __('messages.end_date') }}">
            </div>
            <button type="submit" class="btn-primary w-full btn-sm">{{ __('messages.filter') }}</button>
        </form>
    </div>

    <!-- Transaction List -->
    <div class="card overflow-hidden">
        <div class="section-header">
            <span class="section-header-title">{{ __('messages.transactions') }}</span>
            <span class="badge badge-info">{{ $transactions->count() }}</span>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($transactions as $txn)
                <div class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm
                            @if(in_array($txn['icon'], ['cash_in', 'sale'])) bg-primary-100 dark:bg-primary-900/30
                            @elseif(in_array($txn['icon'], ['cash_out', 'expense'])) bg-danger-100 dark:bg-danger-900/30
                            @else bg-accent-100 dark:bg-accent-900/30 @endif">
                            @if($txn['icon'] === 'cash_in')
                                <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @elseif($txn['icon'] === 'cash_out')
                                <svg class="w-4 h-4 text-danger-600 dark:text-danger-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @elseif($txn['icon'] === 'sale')
                                <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                                </svg>
                            @elseif($txn['icon'] === 'purchase')
                                <svg class="w-4 h-4 text-accent-600 dark:text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                </svg>
                            @else
                                <svg class="w-4 h-4 text-danger-600 dark:text-danger-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $txn['description'] }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">
                                {{ $txn['date']->format('d M Y') }} &middot; {{ $txn['reference'] }}
                            </div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <div class="text-sm font-bold
                            @if(in_array($txn['type'], ['credit_in', 'cash_in'])) text-primary-600 dark:text-primary-400
                            @else text-danger-500 @endif">
                            @if(in_array($txn['type'], ['credit_in', 'cash_in'])) +@else -@endif{{ number_format($txn['amount']) }}
                        </div>
                        <span class="text-[10px] text-ink-400">{{ $txn['currency'] }}</span>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_transactions') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            {{ __('messages.back') }}
        </a>
    </div>
</div>
@endsection
