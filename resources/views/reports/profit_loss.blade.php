@extends('layouts.app')

@section('content')
    @php
        $periodLabels = [
            'month' => __('messages.this_month'),
            '30' => __('messages.last_30_days'),
            'quarter' => __('messages.this_quarter'),
            'year' => __('messages.this_year'),
        ];
        $currencyLabel = $selectedCurrency === 'USD' ? '$' : __('messages.afn');
        $netClass = $netProfit >= 0 ? 'text-primary-700 dark:text-primary-300' : 'text-danger-700 dark:text-danger-300';
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 border border-secondary-100 dark:border-secondary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="trending-up" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.profit_loss_report') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $periodLabels[$period] ?? $periodLabels['month'] }} &middot; {{ $anchorDate }} &middot; {{ $currencyLabel }}</p>
            </div>
        </div>
        <a href="{{ route('dashboard') }}" aria-label="{{ __('messages.back') }}"
           class="w-9 h-9 rounded-xl bg-white dark:bg-[#18191a] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 hover:border-ink-200 dark:hover:border-white/[0.12] transition-all duration-200 active:scale-95">
            <x-icon name="arrow-left" class="w-4 h-4" strokeWidth="2"/>
        </a>
    </div>

    {{-- Filter --}}
    <div class="card p-3 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex gap-2 mb-3 overflow-x-auto pb-0.5">
            @foreach ($periodLabels as $value => $label)
                <a href="{{ route('reports.profit-loss', ['period' => $value, 'currency' => $selectedCurrency, 'date_to' => $anchorDate]) }}"
                   class="badge whitespace-nowrap {{ $period === $value ? 'badge-success' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('reports.profit-loss') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input type="hidden" name="period" value="{{ $period }}">
            <div>
                <label class="form-label !text-xs">{{ __('messages.end_date') }}</label>
                <input type="date" name="date_to" value="{{ $anchorDate }}" class="form-input">
            </div>
            <div>
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <div class="segmented">
                    <button type="button" class="segmented-item {{ $selectedCurrency === 'AFN' ? 'segmented-item-active' : '' }}" onclick="document.getElementById('currency-input').value='AFN';this.parentElement.querySelectorAll('.segmented-item').forEach(b=>b.classList.remove('segmented-item-active'));this.classList.add('segmented-item-active');">{{ __('messages.afn') }}</button>
                    <button type="button" class="segmented-item {{ $selectedCurrency === 'USD' ? 'segmented-item-active' : '' }}" onclick="document.getElementById('currency-input').value='USD';this.parentElement.querySelectorAll('.segmented-item').forEach(b=>b.classList.remove('segmented-item-active'));this.classList.add('segmented-item-active');">{{ __('messages.usd') }}</button>
                    <input type="hidden" name="currency" id="currency-input" value="{{ $selectedCurrency }}">
                </div>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn-primary w-full"><x-icon name="check" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    {{-- Income --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-primary-600"></div>
            <span class="section-header-title">{{ __('messages.income_section') }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3 tabular-nums">
            <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.total_sales') }}</span>
            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">{{ number_format((float) $totalRevenue, 2) }} {{ $currencyLabel }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-ink-100 dark:border-ink-700/30 bg-primary-50/50 dark:bg-primary-900/5 tabular-nums">
            <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.gross_profit') }}</span>
            <span class="text-sm font-bold text-primary-700 dark:text-primary-300">{{ number_format((float) $grossProfit, 2) }} {{ $currencyLabel }}</span>
        </div>
    </div>

    {{-- Expenses --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-danger-500"></div>
            <span class="section-header-title">{{ __('messages.expenses_section') }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.cogs') }}</span><span class="text-sm font-semibold text-danger-600 dark:text-danger-400">{{ number_format((float) $totalCOGS, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.cash_expenses') }}</span><span class="text-sm font-semibold text-danger-600 dark:text-danger-400">{{ number_format((float) $cashExpenses, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.salaries') }}</span><span class="text-sm font-semibold text-danger-600 dark:text-danger-400">{{ number_format((float) $salaryExpenses, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.expenses') }}</span><span class="text-sm font-semibold text-danger-600 dark:text-danger-400">{{ number_format((float) $operatingExpenses, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 bg-danger-50 dark:bg-danger-900/10 font-bold tabular-nums"><span class="text-sm text-ink-900 dark:text-white">{{ __('messages.total_expenses') }}</span><span class="text-sm text-danger-700 dark:text-danger-300">{{ number_format((float) $totalExpenses, 2) }} {{ $currencyLabel }}</span></div>
    </div>

    {{-- Net result --}}
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-4 flex items-center justify-between {{ $netProfit >= 0 ? 'bg-primary-50 dark:bg-primary-900/10' : 'bg-danger-50 dark:bg-danger-900/10' }}">
            <div>
                <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.net_profit') }} / {{ __('messages.loss') }}</span>
                @if($netMarginPercent !== null)
                    <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5">{{ __('messages.net_margin') }}: <span class="font-semibold tabular-nums {{ $netClass }}">{{ number_format((float) $netMarginPercent, 1) }}%</span></p>
                @endif
            </div>
            <span class="text-sm font-bold tabular-nums {{ $netClass }}">{{ number_format((float) $netProfit, 2) }} {{ $currencyLabel }}</span>
        </div>
    </div>
@endsection