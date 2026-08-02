@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.profit_loss_report') }}</h2>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.profit-loss') }}" class="grid grid-cols-3 gap-3">
            <div>
                <label class="form-label !text-xs">{{ __('messages.start_date') }}</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="form-input">
            </div>
            <div>
                <label class="form-label !text-xs">{{ __('messages.end_date') }}</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="form-input">
            </div>
            <div>
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <div class="segmented">
                        <button type="button" class="segmented-item {{ $selectedCurrency === 'AFN' ? 'segmented-item-active' : '' }}" onclick="document.getElementById('currency-input').value='AFN';this.parentElement.querySelectorAll('.segmented-item').forEach(b=>b.classList.remove('segmented-item-active'));this.classList.add('segmented-item-active');">{{ __('messages.afn') }}</button>
                        <button type="button" class="segmented-item {{ $selectedCurrency === 'USD' ? 'segmented-item-active' : '' }}" onclick="document.getElementById('currency-input').value='USD';this.parentElement.querySelectorAll('.segmented-item').forEach(b=>b.classList.remove('segmented-item-active'));this.classList.add('segmented-item-active');">{{ __('messages.usd') }}</button>
                        <input type="hidden" name="currency" id="currency-input" value="{{ $selectedCurrency }}">
                    </div>
            </div>
            <div class="col-span-3">
                <button type="submit" class="btn-primary w-full"><x-icon name="check" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.income_section') }}</h3>
            </div>
        </div>
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.total_sales') }}</span>
            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">{{ number_format($totalRevenue, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span>
        </div>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.expenses_section') }}</h3>
            </div>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.cogs') }}</span><span class="text-sm font-semibold text-danger-600 dark:text-danger-400">{{ number_format($totalCOGS, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.cash_expenses') }}</span><span class="text-sm font-semibold text-danger-600 dark:text-danger-400">{{ number_format($cashExpenses, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30"><span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.salaries') }}</span><span class="text-sm font-semibold text-danger-600 dark:text-danger-400">{{ number_format($salaryExpenses, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 bg-danger-50 dark:bg-danger-900/10 font-bold"><span class="text-sm text-ink-900 dark:text-white">{{ __('messages.total_expenses') }}</span><span class="text-sm text-danger-700 dark:text-danger-300">{{ number_format($totalExpenses, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-4 flex items-center justify-between {{ $netProfit >= 0 ? 'bg-primary-50 dark:bg-primary-900/10' : 'bg-danger-50 dark:bg-danger-900/10' }}">
            <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.net_profit') }} / {{ __('messages.loss') }}</span>
            <span class="text-sm font-bold {{ $netProfit >= 0 ? 'text-primary-700 dark:text-primary-300' : 'text-danger-700 dark:text-danger-300' }}">{{ number_format($netProfit, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span>
        </div>
    </div>
@endsection