@extends('layouts.app')

@section('content')
    @php
        $periodLabels = [
            '7' => __('messages.last_7_days'),
            '30' => __('messages.last_30_days'),
            '90' => __('messages.last_90_days'),
            'month' => __('messages.this_month'),
        ];
        $currencyLabel = $selectedCurrency === 'USD' ? '$' : __('messages.afn');
        $closingClass = $closingBalance >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400';
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="book-open" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.daybook_report') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $periodLabels[$period] ?? $periodLabels['30'] }} &middot; {{ $anchorDate }}</p>
            </div>
        </div>
        <a href="{{ route('dashboard') }}" aria-label="{{ __('messages.back') }}"
           class="w-9 h-9 rounded-xl bg-white dark:bg-[#18191a] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 hover:border-ink-200 dark:hover:border-white/[0.12] transition-all duration-200 active:scale-95">
            <x-icon name="arrow-left" class="w-4 h-4" strokeWidth="2"/>
        </a>
    </div>

    {{-- Filter: end-anchored single date + period chips --}}
    <div class="card p-3 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex gap-2 mb-3 overflow-x-auto pb-0.5">
            @foreach ($periodLabels as $value => $label)
                <a href="{{ route('reports.daybook', ['period' => $value, 'currency' => $selectedCurrency, 'date_to' => $anchorDate]) }}"
                   class="badge whitespace-nowrap {{ $period === $value ? 'badge-success' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('reports.daybook') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input type="hidden" name="period" value="{{ $period }}">
            <div>
                <label class="form-label !text-xs">{{ __('messages.end_date') }}</label>
                <input type="date" name="date_to" value="{{ $anchorDate }}" class="form-input">
            </div>
            <div>
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN" {{ $selectedCurrency === 'AFN' ? 'selected' : '' }}>{{ __('messages.afn') }}</option>
                    <option value="USD" {{ $selectedCurrency === 'USD' ? 'selected' : '' }}>{{ __('messages.usd') }}</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn-primary w-full"><x-icon name="check" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.opening_balance') }}</span>
            <span class="metric-value text-ink-800 dark:text-ink-200 tabular-nums">{{ number_format((float) $openingBalance, 2) }} <span class="text-[11px] font-medium text-ink-400">{{ $currencyLabel }}</span></span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.income') }}</span>
            <span class="metric-value text-primary-600 dark:text-primary-400 tabular-nums">{{ number_format((float) $totalIn, 2) }} <span class="text-[11px] font-medium text-ink-400">{{ $currencyLabel }}</span></span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.expense_out') }}</span>
            <span class="metric-value text-danger-600 dark:text-danger-400 tabular-nums">{{ number_format((float) $totalOut, 2) }} <span class="text-[11px] font-medium text-ink-400">{{ $currencyLabel }}</span></span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.closing_balance') }}</span>
            <span class="metric-value {{ $closingClass }} tabular-nums">{{ number_format((float) $closingBalance, 2) }} <span class="text-[11px] font-medium text-ink-400">{{ $currencyLabel }}</span></span>
        </div>
    </div>

    @foreach($dailyTotals as $day)
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
            <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/50">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-4 rounded-full bg-primary-600 dark:bg-primary-400"></div>
                    <span class="text-sm font-bold text-ink-800 dark:text-ink-200 tabular-nums">{{ \Carbon\Carbon::parse($day['date'])->format('d M Y') }}</span>
                </div>
                <div class="flex gap-3 text-[11px] tabular-nums">
                    <span class="text-primary-600 dark:text-primary-400">{{ __('messages.income') }}: {{ number_format((float) $day['in_total'], 2) }}</span>
                    <span class="text-danger-600 dark:text-danger-400">{{ __('messages.expense_out') }}: {{ number_format((float) $day['out_total'], 2) }}</span>
                    <span class="font-semibold {{ $day['running_balance'] >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">{{ __('messages.balance') }}: {{ number_format((float) $day['running_balance'], 2) }}</span>
                </div>
            </div>
            <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
                @foreach($day['transactions'] as $tx)
                    @php
                        $chip = match ($tx['type']) {
                            'purchase_payment', 'cash_out' => 'badge-danger',
                            'customer_payment', 'cash_in' => 'badge-success',
                            default => 'badge-info',
                        };
                        $isSale = $tx['type'] === 'sale';
                    @endphp
                    <div class="list-row">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="badge whitespace-nowrap {{ $chip }}">{{ $tx['type_label'] }}</span>
                            <span class="text-sm text-ink-800 dark:text-ink-200 min-w-0 truncate">{{ $tx['description'] }}</span>
                        </div>
                        <div class="text-end flex-shrink-0 ms-3 tabular-nums">
                            @if($isSale && (float) ($tx['amount'] ?? 0) > 0)
                                <span class="text-xs font-medium text-ink-400">{{ number_format((float) $tx['amount'], 2) }} {{ $currencyLabel }}</span>
                            @elseif($tx['in_amount'] > 0)
                                <span class="text-sm font-bold text-primary-600 dark:text-primary-400">+{{ number_format((float) $tx['in_amount'], 2) }}</span>
                            @elseif($tx['out_amount'] > 0)
                                <span class="text-sm font-bold text-danger-600 dark:text-danger-400">-{{ number_format((float) $tx['out_amount'], 2) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if($dailyTotals->isEmpty())
        <div class="empty-state">
            <div class="empty-illustration">
                <x-icon name="banknotes" class="w-6 h-6 text-ink-400"/>
            </div>
            <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_transactions_found') }}</p>
        </div>
    @endif
@endsection