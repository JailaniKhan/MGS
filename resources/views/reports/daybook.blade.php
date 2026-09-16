@extends('layouts.app')

@section('content')
    @php
        $currencyLabel = $selectedCurrency === 'USD' ? '$' : __('messages.afn');
        $closingClass = $closingBalance >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400';
        $flowDiff = (float) $closingBalance - (float) $openingBalance;
        $txCount = $dailyTotals->sum(fn ($d) => count($d['transactions']));
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="book-open" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.daybook_report') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $anchorDate }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('dashboard') }}"/>
    </div>

    {{-- Filter card --}}
    <div class="card p-3.5 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.daybook') }}" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div>
                <label class="form-label !text-xs">{{ __('messages.end_date') }}</label>
                <div class="relative">
                    <input type="date" name="date_to" value="{{ $anchorDate }}" class="form-input pe-11">
                    <x-icon name="calendar" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                </div>
            </div>
            <div>
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <div class="relative">
                    <select name="currency" class="form-select form-input pe-11">
                        <option value="AFN" {{ $selectedCurrency === 'AFN' ? 'selected' : '' }}>{{ __('messages.afn') }}</option>
                        <option value="USD" {{ $selectedCurrency === 'USD' ? 'selected' : '' }}>{{ __('messages.usd') }}</option>
                    </select>
                    <x-icon name="chevron-down" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                </div>
            </div>
            <div class="col-span-2 sm:col-span-1 flex items-end">
                <button type="submit" class="btn-primary w-full py-3.5"><x-icon name="check" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    {{-- Summary: closing balance hero + supporting tiles --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.1s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-36 h-36 rounded-full bg-primary-500/[0.07] dark:bg-primary-400/[0.08]"></div>
        <div class="relative">
            <div class="flex items-center justify-between gap-3 mb-2">
                <span class="metric-label">{{ __('messages.closing_balance') }}</span>
                <span class="badge {{ $flowDiff >= 0 ? 'badge-success' : 'badge-danger' }}" dir="ltr">{{ ($flowDiff >= 0 ? '+' : '') . number_format($flowDiff, 2) }}</span>
            </div>
            <p class="text-3xl font-extrabold tabular-nums tracking-tight {{ $closingClass }}" dir="ltr">
                {{ number_format((float) $closingBalance, 2) }} <span class="text-sm font-bold text-ink-400 dark:text-ink-500">{{ $currencyLabel }}</span>
            </p>
            <p class="text-[11px] font-medium text-ink-500 dark:text-ink-400 mt-2.5 flex items-center gap-1.5">
                <x-icon name="receipt-percent" class="w-3.5 h-3.5 text-ink-400"/>
                {{ $txCount }} {{ __('messages.transactions') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-2 mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="card !p-3 relative overflow-hidden">
            <div class="w-6 h-6 rounded-lg bg-ink-100 dark:bg-white/[0.07] flex items-center justify-center mb-2">
                <x-icon name="wallet" class="w-3.5 h-3.5 text-ink-500 dark:text-ink-300" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.opening_balance') }}</span>
            <p class="text-sm font-extrabold tabular-nums text-ink-800 dark:text-ink-100 mt-0.5 truncate" dir="ltr">{{ number_format((float) $openingBalance, 2) }}</p>
        </div>
        <div class="card !p-3 relative overflow-hidden">
            <div class="w-6 h-6 rounded-lg bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center mb-2">
                <x-icon name="arrow-trending-up" class="w-3.5 h-3.5 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.income') }}</span>
            <p class="text-sm font-extrabold tabular-nums text-primary-600 dark:text-primary-400 mt-0.5 truncate" dir="ltr">+{{ number_format((float) $totalIn, 2) }}</p>
        </div>
        <div class="card !p-3 relative overflow-hidden">
            <div class="w-6 h-6 rounded-lg bg-danger-50 dark:bg-danger-900/30 flex items-center justify-center mb-2">
                <x-icon name="arrow-trending-down" class="w-3.5 h-3.5 text-danger-500 dark:text-danger-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.expense_out') }}</span>
            <p class="text-sm font-extrabold tabular-nums text-danger-600 dark:text-danger-400 mt-0.5 truncate" dir="ltr">-{{ number_format((float) $totalOut, 2) }}</p>
        </div>
    </div>

    {{-- Daily ledger --}}
    @foreach($dailyTotals as $day)
        @php
            $isToday = \Carbon\Carbon::parse($day['date'])->isToday();
        @endphp
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.25s;">
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-11 h-11 rounded-xl bg-primary-50 dark:bg-primary-900/25 border border-primary-100 dark:border-primary-800/30 flex flex-col items-center justify-center flex-shrink-0 leading-none">
                        <span class="text-sm font-extrabold tabular-nums text-primary-700 dark:text-primary-300">{{ \Carbon\Carbon::parse($day['date'])->format('d') }}</span>
                        <span class="text-[9px] font-bold uppercase text-primary-600/80 dark:text-primary-400/80 mt-0.5">{{ local_date(\Carbon\Carbon::parse($day['date']), 'M') }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[13px] font-bold text-ink-800 dark:text-ink-100 tabular-nums"><bdi>{{ local_date(\Carbon\Carbon::parse($day['date']), 'd M Y') }}</bdi></span>
                            @if($isToday)
                                <span class="badge badge-info !py-0.5">{{ __('messages.today') }}</span>
                            @endif
                        </div>
                        <p class="text-[10px] font-medium text-ink-400 dark:text-ink-500 mt-0.5">{{ count($day['transactions']) }} {{ __('messages.transactions') }}</p>
                    </div>
                </div>
                <div class="text-end flex-shrink-0">
                    <span class="badge {{ $day['running_balance'] >= 0 ? 'badge-success' : 'badge-danger' }} tabular-nums" dir="ltr">{{ number_format((float) $day['running_balance'], 2) }}</span>
                    <p class="text-[10px] tabular-nums mt-1 whitespace-nowrap" dir="ltr"><span class="font-semibold text-primary-600 dark:text-primary-400">+{{ number_format((float) $day['in_total'], 2) }}</span> <span class="font-semibold text-danger-500 dark:text-danger-400">-{{ number_format((float) $day['out_total'], 2) }}</span></p>
                </div>
            </div>

            <div class="divide-y divide-ink-100 dark:divide-ink-700/25">
                @foreach($day['transactions'] as $tx)
                    @php
                        $isSale = $tx['type'] === 'sale';
                        [$tileIcon, $tileClass] = match ($tx['type']) {
                            'purchase_payment', 'cash_out', 'salary', 'party_payment_out', 'expense' => ['arrow-up-tray', 'bg-danger-50 dark:bg-danger-900/25 text-danger-500 dark:text-danger-400'],
                            'customer_payment', 'cash_in', 'party_payment_in' => ['arrow-down-tray', 'bg-primary-50 dark:bg-primary-900/25 text-primary-600 dark:text-primary-400'],
                            default => ['shopping-cart', 'bg-secondary-50 dark:bg-secondary-900/25 text-secondary-500 dark:text-secondary-400'],
                        };
                    @endphp
                    <div class="list-row">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-xl {{ $tileClass }} flex items-center justify-center flex-shrink-0">
                                <x-icon :name="$tileIcon" class="w-4 h-4" strokeWidth="1.8"/>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-ink-700 dark:text-ink-200 truncate">{{ $tx['type_label'] }}</p>
                                <p class="text-[11px] text-ink-400 dark:text-ink-500 truncate mt-0.5">{{ $tx['description'] }}</p>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0 ms-3 tabular-nums" dir="ltr">
                            @if($isSale && (float) ($tx['amount'] ?? 0) > 0)
                                <span class="text-xs font-semibold text-ink-400 dark:text-ink-500">{{ number_format((float) $tx['amount'], 2) }} {{ $currencyLabel }}</span>
                            @elseif($tx['in_amount'] > 0)
                                <span class="text-sm font-extrabold text-primary-600 dark:text-primary-400">+{{ number_format((float) $tx['in_amount'], 2) }}</span>
                            @elseif($tx['out_amount'] > 0)
                                <span class="text-sm font-extrabold text-danger-600 dark:text-danger-400">-{{ number_format((float) $tx['out_amount'], 2) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if($dailyTotals->isEmpty())
        <x-empty-state title="{{ __('messages.no_transactions_found') }}">
            <x-icon name="banknotes" class="w-6 h-6 text-ink-400"/>
        </x-empty-state>
    @endif
@endsection
