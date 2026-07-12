@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-2">{{ __('messages.balance_sheet') }}</h2>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.balance-sheet') }}" class="grid grid-cols-2 gap-3">
            <div>
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN" {{ $selectedCurrency === 'AFN' ? 'selected' : '' }}>{{ __('messages.afn') }}</option>
                    <option value="USD" {{ $selectedCurrency === 'USD' ? 'selected' : '' }}>{{ __('messages.usd') }}</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn-primary w-full mt-5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 bg-secondary-50 dark:bg-secondary-900/10">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('messages.assets') }}</h3>
            </div>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30"><span class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.cash_in_hand') }}</span><span class="text-sm font-semibold">{{ number_format($cashBalance, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30"><span class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.customer_receivables') }}</span><span class="text-sm font-semibold">{{ number_format($receivables, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30"><span class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.inventory_value') }}</span><span class="text-sm font-semibold">{{ number_format($inventoryValue, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 bg-secondary-100 dark:bg-secondary-900/20 font-bold"><span class="text-sm">{{ __('messages.total_assets') }}</span><span class="text-sm">{{ number_format($totalAssets, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 bg-accent-50 dark:bg-accent-900/10">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('messages.liabilities') }}</h3>
            </div>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30"><span class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.supplier_payables') }}</span><span class="text-sm font-semibold">{{ number_format($payables, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 bg-accent-100 dark:bg-accent-900/20 font-bold"><span class="text-sm">{{ __('messages.total_liabilities') }}</span><span class="text-sm">{{ number_format($payables, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 bg-primary-50 dark:bg-primary-900/10">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('messages.capital_with_paren') }}</h3>
            </div>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30"><span class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.owner_capital') }}</span><span class="text-sm font-semibold">{{ number_format($ownerCapital, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30"><span class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.retained_earnings') }}</span><span class="text-sm font-semibold">{{ number_format($retainedEarnings, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
        <div class="flex items-center justify-between px-4 py-3 bg-primary-100 dark:bg-primary-900/20 font-bold"><span class="text-sm">{{ __('messages.total_equity') }} + {{ __('messages.liabilities') }}</span><span class="text-sm">{{ number_format($totalLiabilitiesEquity, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
    </div>

    <div class="card p-3 text-center text-xs {{ abs($totalAssets - $totalLiabilitiesEquity) < 0.01 ? 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/10' : 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/10' }}">
        @if(abs($totalAssets - $totalLiabilitiesEquity) < 0.01)
            ✓ {{ __('messages.assets_section') }} = {{ __('messages.capital') }} + {{ __('messages.liabilities_balanced') }}
        @else
            ⚠ {{ __('messages.assets_with_paren') }}{{ number_format($totalAssets, 2) }}) ≠ {{ __('messages.capital') }} + {{ __('messages.liabilities') }} ({{ number_format($totalLiabilitiesEquity, 2) }})
        @endif
    </div>
@endsection