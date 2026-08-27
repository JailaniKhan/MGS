@extends('layouts.app')

@section('content')
    @php
        $currencyLabel = $selectedCurrency === 'USD' ? '$' : __('messages.afn');
        $balanced = abs((float) $totalAssets - (float) $totalLiabilitiesEquity) < 0.01;

        $shareOf = function ($amount, $total) {
            if (bccomp((string) $total, '0', 2) !== 1) return 0;
            return (int) min(100, round(bcmul(bcdiv((string) $amount, (string) $total, 4), '100', 0)));
        };
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 border border-secondary-100 dark:border-secondary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="scale" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.balance_sheet') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $currencyLabel }} &middot; <bdi>{{ local_date(now(), 'd M Y') }}</bdi></p>
            </div>
        </div>
        <x-back-button href="{{ route('dashboard') }}"/>
    </div>

    {{-- Filter --}}
    <div class="card p-3.5 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.balance-sheet') }}" class="grid grid-cols-2 gap-3">
            <div>
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <div class="segmented">
                    <button type="button" class="segmented-item {{ $selectedCurrency === 'AFN' ? 'segmented-item-active' : '' }}" onclick="document.getElementById('currency-input').value='AFN';this.parentElement.querySelectorAll('.segmented-item').forEach(b=>b.classList.remove('segmented-item-active'));this.classList.add('segmented-item-active');">{{ __('messages.afn') }}</button>
                    <button type="button" class="segmented-item {{ $selectedCurrency === 'USD' ? 'segmented-item-active' : '' }}" onclick="document.getElementById('currency-input').value='USD';this.parentElement.querySelectorAll('.segmented-item').forEach(b=>b.classList.remove('segmented-item-active'));this.classList.add('segmented-item-active');">{{ __('messages.usd') }}</button>
                    <input type="hidden" name="currency" id="currency-input" value="{{ $selectedCurrency }}">
                </div>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn-primary w-full py-3.5"><x-icon name="check" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    {{-- Hero: total assets --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.1s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-36 h-36 rounded-full bg-primary-500/[0.07] dark:bg-primary-400/[0.08]"></div>
        <div class="relative">
            <div class="flex items-center justify-between gap-3 mb-2">
                <span class="metric-label">{{ __('messages.total_assets') }}</span>
                <span class="badge {{ $balanced ? 'badge-success' : 'badge-danger' }}">
                    <x-icon name="{{ $balanced ? 'shield-check' : 'exclamation-circle' }}" class="w-3.5 h-3.5" strokeWidth="2"/>
                    {{ $balanced ? __('messages.balance_sheet') : '≠' }}
                </span>
            </div>
            <p class="text-3xl font-extrabold tabular-nums tracking-tight text-primary-600 dark:text-primary-400" dir="ltr">
                {{ number_format((float) $totalAssets, 2) }} <span class="text-sm font-bold text-ink-400 dark:text-ink-500">{{ $currencyLabel }}</span>
            </p>
            <p class="mt-2.5 flex items-center gap-1.5 text-[11px] font-medium text-ink-500 dark:text-ink-400">
                <x-icon name="scale" class="w-3.5 h-3.5 text-ink-400"/>
                {{ __('messages.total_equity') }} + {{ __('messages.liabilities') }}: <span class="font-bold tabular-nums text-ink-700 dark:text-ink-200" dir="ltr">{{ number_format((float) $totalLiabilitiesEquity, 2) }} {{ $currencyLabel }}</span>
            </p>
        </div>
    </div>

    {{-- Supporting tiles --}}
    <div class="grid grid-cols-2 gap-2 mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="card !p-3 relative overflow-hidden">
            <div class="w-6 h-6 rounded-lg bg-danger-50 dark:bg-danger-900/30 flex items-center justify-center mb-2">
                <x-icon name="arrow-up-tray" class="w-3.5 h-3.5 text-danger-500 dark:text-danger-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.total_liabilities') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums text-danger-600 dark:text-danger-400" dir="ltr">{{ number_format((float) $payables, 2) }}</p>
        </div>
        <div class="card !p-3 relative overflow-hidden">
            <div class="w-6 h-6 rounded-lg bg-secondary-50 dark:bg-secondary-900/30 flex items-center justify-center mb-2">
                <x-icon name="banknotes" class="w-3.5 h-3.5 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.total_equity') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums text-ink-800 dark:text-ink-100" dir="ltr">{{ number_format((float) $totalEquity, 2) }}</p>
        </div>
    </div>

    {{-- Assets --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-primary-600"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.assets') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/25">
            @foreach ([
                ['icon' => 'banknotes', 'label' => __('messages.cash_in_hand'), 'amount' => $cashBalance],
                ['icon' => 'inbox-arrow-down', 'label' => __('messages.customer_receivables'), 'amount' => $receivables],
                ['icon' => 'archive-box', 'label' => __('messages.inventory_value'), 'amount' => $inventoryValue],
            ] as $row)
                @php
                    $share = $shareOf($row['amount'], $totalAssets);
                @endphp
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between gap-3 tabular-nums">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-primary-50 dark:bg-primary-900/25 text-primary-600 dark:text-primary-400 flex items-center justify-center flex-shrink-0">
                                <x-icon :name="$row['icon']" class="w-4 h-4" strokeWidth="1.8"/>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-ink-700 dark:text-ink-300 truncate">{{ $row['label'] }}</p>
                                @if($share > 0)
                                    <p class="text-[10px] font-semibold text-ink-400 dark:text-ink-500 tabular-nums mt-0.5">{{ $share }}%</p>
                                @endif
                            </div>
                        </div>
                        <span class="text-sm font-bold text-ink-900 dark:text-ink-100 flex-shrink-0 ms-3" dir="ltr">{{ number_format((float) $row['amount'], 2) }} {{ $currencyLabel }}</span>
                    </div>
                    @if($share > 0)
                        <div class="mt-2 h-1 rounded-full bg-ink-100 dark:bg-white/[0.06] overflow-hidden">
                            <div class="h-full rounded-full bg-primary-400 dark:bg-primary-500/70" style="width: {{ $share }}%;"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="flex items-center justify-between px-4 py-3.5 bg-primary-50/50 dark:bg-primary-900/5 tabular-nums">
            <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.total_assets') }}</span>
            <span class="text-sm font-extrabold text-primary-700 dark:text-primary-300" dir="ltr">{{ number_format((float) $totalAssets, 2) }} {{ $currencyLabel }}</span>
        </div>
    </div>

    {{-- Liabilities --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.25s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-danger-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.liabilities') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/25">
            @foreach ([
                ['icon' => 'truck', 'label' => __('messages.supplier_payables'), 'amount' => $payables],
            ] as $row)
                @php
                    $share = $shareOf($row['amount'], $totalLiabilitiesEquity);
                @endphp
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between gap-3 tabular-nums">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-danger-50 dark:bg-danger-900/25 text-danger-500 dark:text-danger-400 flex items-center justify-center flex-shrink-0">
                                <x-icon :name="$row['icon']" class="w-4 h-4" strokeWidth="1.8"/>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-ink-700 dark:text-ink-300 truncate">{{ $row['label'] }}</p>
                                @if($share > 0)
                                    <p class="text-[10px] font-semibold text-ink-400 dark:text-ink-500 tabular-nums mt-0.5">{{ $share }}%</p>
                                @endif
                            </div>
                        </div>
                        <span class="text-sm font-bold text-danger-600 dark:text-danger-400 flex-shrink-0 ms-3" dir="ltr">{{ number_format((float) $row['amount'], 2) }} {{ $currencyLabel }}</span>
                    </div>
                    @if($share > 0)
                        <div class="mt-2 h-1 rounded-full bg-ink-100 dark:bg-white/[0.06] overflow-hidden">
                            <div class="h-full rounded-full bg-danger-400 dark:bg-danger-500/70" style="width: {{ $share }}%;"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="flex items-center justify-between px-4 py-3.5 bg-danger-50 dark:bg-danger-900/10 tabular-nums">
            <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.total_liabilities') }}</span>
            <span class="text-sm font-extrabold text-danger-700 dark:text-danger-300" dir="ltr">{{ number_format((float) $payables, 2) }} {{ $currencyLabel }}</span>
        </div>
    </div>

    {{-- Equity --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.3s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-secondary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.capital') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/25">
            @foreach ([
                ['icon' => 'user', 'label' => __('messages.owner_capital'), 'amount' => $ownerCapital],
                ['icon' => 'chart-pie', 'label' => __('messages.retained_earnings'), 'amount' => $retainedEarnings],
            ] as $row)
                @php
                    $share = $shareOf($row['amount'], $totalLiabilitiesEquity);
                @endphp
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between gap-3 tabular-nums">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-secondary-50 dark:bg-secondary-900/25 text-secondary-600 dark:text-secondary-400 flex items-center justify-center flex-shrink-0">
                                <x-icon :name="$row['icon']" class="w-4 h-4" strokeWidth="1.8"/>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-ink-700 dark:text-ink-300 truncate">{{ $row['label'] }}</p>
                                @if($share > 0)
                                    <p class="text-[10px] font-semibold text-ink-400 dark:text-ink-500 tabular-nums mt-0.5">{{ $share }}%</p>
                                @endif
                            </div>
                        </div>
                        <span class="text-sm font-bold text-ink-900 dark:text-ink-100 flex-shrink-0 ms-3" dir="ltr">{{ number_format((float) $row['amount'], 2) }} {{ $currencyLabel }}</span>
                    </div>
                    @if($share > 0)
                        <div class="mt-2 h-1 rounded-full bg-ink-100 dark:bg-white/[0.06] overflow-hidden">
                            <div class="h-full rounded-full bg-secondary-400 dark:bg-secondary-500/70" style="width: {{ $share }}%;"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="flex items-center justify-between px-4 py-3.5 bg-primary-50/50 dark:bg-primary-900/5 tabular-nums">
            <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.total_equity') }} + {{ __('messages.liabilities') }}</span>
            <span class="text-sm font-extrabold text-primary-700 dark:text-primary-300" dir="ltr">{{ number_format((float) $totalLiabilitiesEquity, 2) }} {{ $currencyLabel }}</span>
        </div>
    </div>

    {{-- Balance check --}}
    <div class="card p-4 page-enter {{ $balanced ? 'bg-primary-50 dark:bg-primary-900/10 border-primary-100 dark:border-primary-800/30' : 'bg-danger-50 dark:bg-danger-900/10 border-danger-200 dark:border-danger-800/30' }}" style="animation-delay: 0.35s;">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-xl {{ $balanced ? 'bg-primary-100 dark:bg-primary-900/30' : 'bg-danger-100 dark:bg-danger-900/30' }} flex items-center justify-center flex-shrink-0">
                <x-icon name="{{ $balanced ? 'shield-check' : 'x-circle' }}" class="w-4 h-4 {{ $balanced ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}" strokeWidth="2"/>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold tabular-nums {{ $balanced ? 'text-primary-700 dark:text-primary-300' : 'text-danger-700 dark:text-danger-300' }}" dir="ltr">
                    {{ number_format((float) $totalAssets, 2) }} {{ $balanced ? '=' : '≠' }} {{ number_format((float) $totalLiabilitiesEquity, 2) }} {{ $currencyLabel }}
                </p>
                <p class="text-[11px] font-medium {{ $balanced ? 'text-primary-600/80 dark:text-primary-400/80' : 'text-danger-600/80 dark:text-danger-400/80' }} mt-0.5">
                    {{ __('messages.assets_section') }} {{ $balanced ? '=' : '≠' }} {{ __('messages.liabilities') }} + {{ __('messages.capital') }}
                </p>
            </div>
        </div>
    </div>
@endsection
