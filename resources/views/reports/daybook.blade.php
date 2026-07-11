@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-2">{{ __('messages.daybook_report') }}</h2>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.daybook') }}" class="grid grid-cols-3 gap-3">
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
                <select name="currency" class="form-input">
                    <option value="AFN" {{ $selectedCurrency === 'AFN' ? 'selected' : '' }}>{{ __('messages.afn') }}</option>
                    <option value="USD" {{ $selectedCurrency === 'USD' ? 'selected' : '' }}>{{ __('messages.usd') }}</option>
                </select>
            </div>
            <div class="col-span-3">
                <button type="submit" class="btn-primary w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="grid grid-cols-3 gap-2 text-xs text-center">
            <div><span class="text-gray-500 dark:text-gray-400">{{ __('messages.opening_balance') }}</span><p class="font-bold text-gray-800 dark:text-gray-200">{{ number_format($openingBalance, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</p></div>
            <div><span class="text-primary-600 dark:text-primary-400">{{ __('messages.income') }}</span><p class="font-bold text-primary-600 dark:text-primary-400">{{ number_format($totalIn, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</p></div>
            <div><span class="text-red-600 dark:text-red-400">{{ __('messages.expense_out') }}</span><p class="font-bold text-red-600 dark:text-red-400">{{ number_format($totalOut, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</p></div>
        </div>
    </div>

    @foreach($dailyTotals as $day)
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ $day['date'] }}</span>
                    <div class="flex gap-3 text-[11px]">
                        <span class="text-primary-600 dark:text-primary-400">{{ __('messages.income') }}: {{ number_format($day['in_total'], 2) }}</span>
                        <span class="text-red-600 dark:text-red-400">{{ __('messages.expense_out') }}: {{ number_format($day['out_total'], 2) }}</span>
                        <span class="font-semibold {{ $day['running_balance'] >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-red-600 dark:text-red-400' }}">{{ __('messages.balance') }}: {{ number_format($day['running_balance'], 2) }}</span>
                    </div>
                </div>
            </div>
            @foreach($day['transactions'] as $tx)
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <span class="text-sm text-gray-800 dark:text-gray-200">{{ $tx['description'] }}</span>
                        <span class="text-[11px] text-gray-500 dark:text-gray-400 block">{{ $tx['type_label'] ?? '' }}</span>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        @if($tx['in_amount'] > 0)
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">+{{ number_format($tx['in_amount'], 2) }}</span>
                        @elseif($tx['out_amount'] > 0)
                            <span class="text-sm font-bold text-red-600 dark:text-red-400">-{{ number_format($tx['out_amount'], 2) }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    @if($dailyTotals->isEmpty())
        <div class="empty-state">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('messages.no_transactions_found') }}</p>
        </div>
    @endif
@endsection