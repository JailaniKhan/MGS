@extends('layouts.app')

@section('content')
<div class="page-enter">
    {{-- Header --}}
    <div class="page-header">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="book-open" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="page-title leading-tight">{{ __('messages.passbook') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">
                    @if($dateFrom || $dateTo)
                        {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : '...' }}
                        &ndash;
                        {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : '...' }}
                    @else
                        {{ __('messages.all') }}
                    @endif
                </p>
            </div>
        </div>
        <a href="{{ route('dashboard') }}" aria-label="{{ __('messages.back') }}"
           class="w-9 h-9 rounded-xl bg-white dark:bg-[#18191a] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 hover:border-ink-200 dark:hover:border-white/[0.12] transition-all duration-200 active:scale-95">
            <x-icon name="arrow-left" class="w-4 h-4" strokeWidth="2"/>
        </a>
    </div>

    {{-- Cash summary --}}
    <div class="card p-4 page-enter" style="animation-delay: 0.05s;">
        <div class="grid grid-cols-2 gap-3">
            <div class="min-w-0">
                <span class="metric-label">{{ __('messages.total_in') }}</span>
                <div class="metric-value text-primary-600 dark:text-primary-400 mt-1">
                    {{ number_format((float) $totalIn, 2) }}
                </div>
                <div class="text-[10px] font-semibold text-ink-400 dark:text-ink-500 mt-0.5">
                    {{ __('messages.afn') }}
                    @if($totalInUSD > 0)
                        <span class="text-primary-500 dark:text-primary-400">+ {{ number_format((float) $totalInUSD, 2) }} USD</span>
                    @endif
                </div>
            </div>
            <div class="min-w-0 text-end">
                <span class="metric-label">{{ __('messages.total_out') }}</span>
                <div class="metric-value text-danger-500 dark:text-danger-400 mt-1">
                    {{ number_format((float) $totalOut, 2) }}
                </div>
                <div class="text-[10px] font-semibold text-ink-400 dark:text-ink-500 mt-0.5">
                    {{ __('messages.afn') }}
                    @if($totalOutUSD > 0)
                        <span class="text-danger-500 dark:text-danger-400">+ {{ number_format((float) $totalOutUSD, 2) }} USD</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-3 mt-3 page-enter" style="animation-delay: 0.1s;">
        <form method="GET" action="{{ route('passbook.index') }}">
            @php
                $filters = ['all', 'cash_in', 'cash_out', 'expense'];
                $rangeParams = array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo]);
            @endphp
            <div class="rail mb-3">
                @foreach ($filters as $f)
                    <a href="{{ route('passbook.index', array_filter(['filter' => $f] + $rangeParams)) }}"
                       class="badge whitespace-nowrap {{ $filter === $f
                           ? ($f === 'all' || $f === 'cash_in' ? 'badge-success' : 'badge-danger')
                           : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400 hover:bg-ink-200 dark:hover:bg-ink-700' }}">
                        {{ __('messages.' . $f) }}
                    </a>
                @endforeach
            </div>
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div>
                    <label for="date_from" class="form-label">{{ __('messages.start_date') }}</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="form-input text-xs">
                </div>
                <div>
                    <label for="date_to" class="form-label">{{ __('messages.end_date') }}</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="form-input text-xs">
                </div>
            </div>
            <button type="submit" class="btn-primary w-full btn-sm">{{ __('messages.filter') }}</button>
        </form>
    </div>

    {{-- Transaction list --}}
    <div class="card overflow-hidden mt-3 page-enter" style="animation-delay: 0.15s;">
        <div class="section-header">
            <span class="section-header-title">{{ __('messages.transactions') }}</span>
            <span class="badge badge-info">{{ method_exists($transactions, 'total') ? $transactions->total() : $transactions->count() }}</span>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-white/[0.05] stagger">
            @forelse ($transactions as $txn)
                @php
                    $isIn = $txn['type'] === 'cash_in';
                    $tone = $isIn ? 'primary' : 'danger';
                @endphp
                <div class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-10 h-10 rounded-[0.875rem] flex items-center justify-center flex-shrink-0 border
                            @if($tone === 'primary') bg-primary-50 dark:bg-primary-900/30 border-primary-100 dark:border-primary-800/40 text-primary-600 dark:text-primary-400
                            @else bg-danger-50 dark:bg-danger-900/30 border-danger-100 dark:border-danger-800/40 text-danger-600 dark:text-danger-400 @endif">
                            @if($txn['icon'] === 'cash_in')
                                <x-icon name="currency-dollar" class="w-4.5 h-4.5" strokeWidth="1.8"/>
                            @elseif($txn['icon'] === 'cash_out')
                                <x-icon name="minus-circle" class="w-4.5 h-4.5" strokeWidth="1.8"/>
                            @else
                                <x-icon name="banknotes" class="w-4.5 h-4.5" strokeWidth="1.8"/>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $txn['description'] }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400 flex items-center gap-1.5 mt-0.5 whitespace-nowrap">
                                <span>{{ $txn['date']->format('d M Y') }}</span>
                                <span class="text-ink-300 dark:text-white/[0.12]">&middot;</span>
                                <span class="font-semibold text-ink-400 dark:text-ink-500">{{ $txn['reference'] }}</span>
                                <span class="chip flex-shrink-0">{{ __('messages.' . $txn['type']) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold tabular-nums {{ $isIn ? 'text-primary-600 dark:text-primary-400' : 'text-danger-500 dark:text-danger-400' }}">
                            {{ $isIn ? '+' : '-' }}{{ number_format((float) $txn['amount'], 2) }}
                        </div>
                        <span class="text-[10px] font-semibold text-ink-400">{{ $txn['currency'] }}</span>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="empty-illustration">
                        <x-icon name="book-open" class="w-6 h-6 text-ink-400"/>
                    </div>
                    <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_transactions') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($transactions->hasPages())
        <div class="mt-4">{{ $transactions->links() }}</div>
    @endif
</div>
@endsection
