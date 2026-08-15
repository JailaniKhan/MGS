@extends('layouts.app')

@section('content')
    @php
        $currencyLabel = $selectedCurrency === 'USD' ? '$' : __('messages.afn');
        $balanced = abs((float) $totalAssets - (float) $totalLiabilitiesEquity) < 0.01;
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 border border-secondary-100 dark:border-secondary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="scale" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.balance_sheet') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $currencyLabel }}</p>
            </div>
        </div>
        <a href="{{ route('dashboard') }}" aria-label="{{ __('messages.back') }}"
           class="w-9 h-9 rounded-xl bg-white dark:bg-[#18191a] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 hover:border-ink-200 dark:hover:border-white/[0.12] transition-all duration-200 active:scale-95">
            <x-icon name="arrow-left" class="w-4 h-4" strokeWidth="2"/>
        </a>
    </div>

    {{-- Filter --}}
    <div class="card p-3 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.balance-sheet') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
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

    {{-- Summary --}}
    <div class="grid grid-cols-3 gap-2 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_assets') }}</span>
            <span class="metric-value text-primary-600 dark:text-primary-400 tabular-nums">{{ number_format((float) $totalAssets, 2) }} <span class="text-[11px] font-medium text-ink-400">{{ $currencyLabel }}</span></span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_liabilities') }}</span>
            <span class="metric-value text-danger-600 dark:text-danger-400 tabular-nums">{{ number_format((float) $payables, 2) }} <span class="text-[11px] font-medium text-ink-400">{{ $currencyLabel }}</span></span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_equity') }}</span>
            <span class="metric-value text-ink-800 dark:text-ink-200 tabular-nums">{{ number_format((float) $totalEquity, 2) }} <span class="text-[11px] font-medium text-ink-400">{{ $currencyLabel }}</span></span>
        </div>
    </div>

    {{-- Assets --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-primary-600"></div>
            <span class="section-header-title">{{ __('messages.assets') }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.cash_in_hand') }}</span><span class="text-sm font-semibold">{{ number_format((float) $cashBalance, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-ink-100 dark:border-ink-700/30 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.customer_receivables') }}</span><span class="text-sm font-semibold">{{ number_format((float) $receivables, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-ink-100 dark:border-ink-700/30 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.inventory_value') }}</span><span class="text-sm font-semibold">{{ number_format((float) $inventoryValue, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-ink-100 dark:border-ink-700/30 bg-primary-50/50 dark:bg-primary-900/5 font-bold tabular-nums"><span class="text-sm text-ink-900 dark:text-white">{{ __('messages.total_assets') }}</span><span class="text-sm text-primary-700 dark:text-primary-300">{{ number_format((float) $totalAssets, 2) }} {{ $currencyLabel }}</span></div>
    </div>

    {{-- Liabilities --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-danger-500"></div>
            <span class="section-header-title">{{ __('messages.liabilities') }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.supplier_payables') }}</span><span class="text-sm font-semibold">{{ number_format((float) $payables, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-ink-100 dark:border-ink-700/30 bg-danger-50 dark:bg-danger-900/10 font-bold tabular-nums"><span class="text-sm text-ink-900 dark:text-white">{{ __('messages.total_liabilities') }}</span><span class="text-sm text-danger-700 dark:text-danger-300">{{ number_format((float) $payables, 2) }} {{ $currencyLabel }}</span></div>
    </div>

    {{-- Equity --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.25s;">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-secondary-500"></div>
            <span class="section-header-title">{{ __('messages.capital') }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.owner_capital') }}</span><span class="text-sm font-semibold">{{ number_format((float) $ownerCapital, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-ink-100 dark:border-ink-700/30 tabular-nums"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.retained_earnings') }}</span><span class="text-sm font-semibold">{{ number_format((float) $retainedEarnings, 2) }} {{ $currencyLabel }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-t border-ink-100 dark:border-ink-700/30 bg-primary-50/50 dark:bg-primary-900/5 font-bold tabular-nums"><span class="text-sm text-ink-900 dark:text-white">{{ __('messages.total_equity') }} + {{ __('messages.liabilities') }}</span><span class="text-sm text-primary-700 dark:text-primary-300">{{ number_format((float) $totalLiabilitiesEquity, 2) }} {{ $currencyLabel }}</span></div>
    </div>

    {{-- Balance check --}}
    <div class="card p-4 page-enter flex items-center gap-2.5 {{ $balanced ? 'bg-primary-50 dark:bg-primary-900/10' : 'bg-danger-50 dark:bg-danger-900/10' }}" style="animation-delay: 0.3s;">
        @if($balanced)
            <x-icon name="shield-check" class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0" strokeWidth="2"/>
            <span class="text-xs font-semibold text-primary-700 dark:text-primary-300">{{ __('messages.assets_section') }} = {{ __('messages.capital') }} + {{ __('messages.liabilities_balanced') }}</span>
        @else
            <x-icon name="x-circle" class="w-4 h-4 text-danger-600 dark:text-danger-400 flex-shrink-0" strokeWidth="2"/>
            <span class="text-xs font-semibold text-danger-700 dark:text-danger-300">{{ __('messages.assets_section') }} ({{ number_format((float) $totalAssets, 2) }}) ≠ {{ __('messages.capital') }} + {{ __('messages.liabilities') }} ({{ number_format((float) $totalLiabilitiesEquity, 2) }})</span>
        @endif
    </div>
@endsection