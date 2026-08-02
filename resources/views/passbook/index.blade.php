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
            <span class="badge badge-info">{{ method_exists($transactions, 'total') ? $transactions->total() : $transactions->count() }}</span>
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
                                <x-icon name="currency-dollar" class="w-4 h-4 text-primary-600 dark:text-primary-400"/>
                            @elseif($txn['icon'] === 'cash_out')
                                <x-icon name="minus-circle" class="w-4 h-4 text-danger-600 dark:text-danger-400"/>
                            @elseif($txn['icon'] === 'sale')
                                <x-icon name="shopping-cart" class="w-4 h-4 text-primary-600 dark:text-primary-400"/>
                            @elseif($txn['icon'] === 'purchase')
                                <x-icon name="shopping-bag" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                            @else
                                <x-icon name="currency-dollar" class="w-4 h-4 text-danger-600 dark:text-danger-400"/>
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
                        <x-icon name="currency-dollar" class="w-6 h-6 text-ink-400"/>
                    </div>
                    <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_transactions') }}</p>
                </div>
            @endforelse
        </div>
    </div>
    @if ($transactions->hasPages())
        <div class="mt-4">{{ $transactions->links() }}</div>
    @endif

    <div class="mt-4 text-center">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>
            {{ __('messages.back') }}
        </a>
    </div>
</div>
@endsection
