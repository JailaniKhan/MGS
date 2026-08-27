@extends('layouts.app')

@php
    $tileStyles = [
        'bg-primary-500/10 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400',
        'bg-accent-500/10 dark:bg-accent-500/15 text-accent-600 dark:text-accent-400',
        'bg-secondary-500/10 dark:bg-secondary-500/15 text-secondary-600 dark:text-secondary-400',
    ];
@endphp

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="banknotes" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.expenses') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $expenses->total() }} {{ __('messages.expense') }}</p>
            </div>
        </div>
        <a href="{{ route('expenses.create') }}" class="btn-primary btn-sm flex-shrink-0">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_expense') }}
        </a>
    </div>

    {{-- Month summary --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-accent-500/[0.08] dark:bg-accent-400/[0.08]"></div>
        <div class="relative">
            <div class="flex items-center gap-1.5">
                <x-icon name="calendar" class="w-3.5 h-3.5 text-ink-400 dark:text-ink-500" strokeWidth="1.8"/>
                <span class="metric-label">{{ __('messages.this_month') }}</span>
            </div>
            <p class="mt-1 text-3xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-white" dir="ltr">
                {{ number_format($monthTotalAFN) }} <span class="text-sm font-bold text-ink-400 dark:text-ink-500">{{ __('messages.afn') }}</span>
                @if ($monthTotalUSD > 0)
                    <span class="text-base font-bold text-ink-300 dark:text-ink-600 mx-1">&middot;</span>
                    {{ number_format($monthTotalUSD) }} <span class="text-sm font-bold text-ink-400 dark:text-ink-500">$</span>
                @endif
            </p>
            <p class="mt-2 flex items-center gap-1.5 text-[11px] font-medium text-ink-500 dark:text-ink-400">
                <x-icon name="tag" class="w-3.5 h-3.5 text-ink-400" strokeWidth="1.8"/>
                {{ $monthCount }} {{ __('messages.expense') }}
            </p>
        </div>
    </div>

    {{-- Month stat tiles --}}
    <div class="grid grid-cols-2 gap-2 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="card !p-3">
            <div class="w-6 h-6 rounded-lg bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center mb-2">
                <x-icon name="banknotes" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.afn') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums text-ink-800 dark:text-ink-100">{{ number_format($monthTotalAFN) }}</p>
        </div>
        <div class="card !p-3">
            <div class="w-6 h-6 rounded-lg bg-primary-500/10 dark:bg-primary-500/15 flex items-center justify-center mb-2">
                <x-icon name="currency-dollar" class="w-3.5 h-3.5 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.usd') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums text-ink-800 dark:text-ink-100">{{ number_format($monthTotalUSD) }}</p>
        </div>
    </div>

    {{-- Search --}}
    <div class="search-bar sticky top-[3.5rem] z-10 mb-3 page-enter" style="animation-delay: 0.13s;">
        <x-icon name="magnifying-glass" class="search-icon" strokeWidth="1.8"/>
        <input type="search" inputmode="search"
               data-list-filter="expense-list"
               data-empty-text="{{ __('messages.no_results') }}"
               autocomplete="off"
               placeholder="{{ __('messages.search') }}"
               class="flex-1">
    </div>

    {{-- List --}}
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.15s;" id="expense-list">
        @forelse ($expenses as $expense)
            @php $tile = $tileStyles[crc32($expense->category) % count($tileStyles)]; @endphp
            <a href="{{ route('expenses.show', $expense) }}" class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl {{ $tile }} flex items-center justify-center flex-shrink-0">
                        <x-icon name="tag" class="w-4 h-4" strokeWidth="1.8"/>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $expense->category }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400 truncate">
                            <bdi>{{ local_date($expense->expense_date, 'd M Y') }}</bdi>
                            @if($expense->notes) &middot; {{ Str::limit($expense->notes, 25) }} @endif
                        </div>
                    </div>
                </div>
                <div class="text-end flex-shrink-0 ms-3">
                    <div class="text-sm font-extrabold tabular-nums text-danger-600 dark:text-danger-400"><bdi>-{{ number_format($expense->amount) }}</bdi></div>
                    <div class="text-[10px] font-medium text-ink-400 dark:text-ink-500">{{ $expense->currency_symbol }}</div>
                </div>
            </a>
        @empty
            <x-empty-state title="{{ __('messages.no_expenses') }}">
                <x-icon name="banknotes" class="w-6 h-6 text-ink-400"/>
            </x-empty-state>
        @endforelse
    </div>

    @if($expenses->hasPages())
        <div class="mt-4">
            {{ $expenses->links() }}
        </div>
    @endif
@endsection
