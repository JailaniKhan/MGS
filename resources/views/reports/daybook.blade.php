@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.daybook_report') }}</h2>
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
                <button type="submit" class="btn-primary w-full"><x-icon name="check" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="grid grid-cols-3 gap-2 text-xs text-center">
            <div><span class="text-ink-500 dark:text-ink-400">{{ __('messages.opening_balance') }}</span><p class="font-bold text-ink-800 dark:text-ink-200">{{ number_format($openingBalance, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</p></div>
            <div><span class="text-primary-600 dark:text-primary-400">{{ __('messages.income') }}</span><p class="font-bold text-primary-600 dark:text-primary-400">{{ number_format($totalIn, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</p></div>
            <div><span class="text-danger-600 dark:text-danger-400">{{ __('messages.expense_out') }}</span><p class="font-bold text-danger-600 dark:text-danger-400">{{ number_format($totalOut, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</p></div>
        </div>
    </div>

    @foreach($dailyTotals as $day)
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/50">
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-bold text-ink-800 dark:text-ink-200">{{ $day['date'] }}</span>
                    <div class="flex gap-3 text-[11px]">
                        <span class="text-primary-600 dark:text-primary-400">{{ __('messages.income') }}: {{ number_format($day['in_total'], 2) }}</span>
                        <span class="text-danger-600 dark:text-danger-400">{{ __('messages.expense_out') }}: {{ number_format($day['out_total'], 2) }}</span>
                        <span class="font-semibold {{ $day['running_balance'] >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">{{ __('messages.balance') }}: {{ number_format($day['running_balance'], 2) }}</span>
                    </div>
                </div>
            </div>
            @foreach($day['transactions'] as $tx)
                <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <span class="text-sm text-ink-800 dark:text-ink-200">{{ $tx['description'] }}</span>
                        <span class="text-[11px] text-ink-500 dark:text-ink-400 block">{{ $tx['type_label'] ?? '' }}</span>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        @if($tx['in_amount'] > 0)
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">+{{ number_format($tx['in_amount'], 2) }}</span>
                        @elseif($tx['out_amount'] > 0)
                            <span class="text-sm font-bold text-danger-600 dark:text-danger-400">-{{ number_format($tx['out_amount'], 2) }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    @if($dailyTotals->isEmpty())
        <div class="empty-state">
            <p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_transactions_found') }}</p>
        </div>
    @endif
@endsection