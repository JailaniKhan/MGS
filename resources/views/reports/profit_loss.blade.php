@extends('layouts.app')

@section('content')
    @php
        $currencyLabel = $selectedCurrency === 'USD' ? '$' : __('messages.afn');
        $netPositive = bccomp((string) $netProfit, '0', 2) >= 0;
        $netClass = $netPositive ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400';

        $shareOfTotal = function ($amount) use ($totalExpenses) {
            if (bccomp((string) $totalExpenses, '0', 2) !== 1) return 0;
            return (int) min(100, round(bcmul(bcdiv((string) $amount, (string) $totalExpenses, 4), '100', 0)));
        };
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 border border-secondary-100 dark:border-secondary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="arrow-trending-up" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.profit_loss_report') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.all_time') }} &middot; {{ $anchorDate }} &middot; {{ $currencyLabel }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('dashboard') }}"/>
    </div>

    {{-- Filter --}}
    <div class="card p-3.5 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.profit-loss') }}" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div>
                <label class="form-label !text-xs">{{ __('messages.end_date') }}</label>
                <div class="relative">
                    <input type="date" name="date_to" value="{{ $anchorDate }}" class="form-input pe-11">
                    <x-icon name="calendar" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                </div>
            </div>
            <div>
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <div class="segmented">
                    <button type="button" class="segmented-item {{ $selectedCurrency === 'AFN' ? 'segmented-item-active' : '' }}" onclick="document.getElementById('currency-input').value='AFN';this.parentElement.querySelectorAll('.segmented-item').forEach(b=>b.classList.remove('segmented-item-active'));this.classList.add('segmented-item-active');">{{ __('messages.afn') }}</button>
                    <button type="button" class="segmented-item {{ $selectedCurrency === 'USD' ? 'segmented-item-active' : '' }}" onclick="document.getElementById('currency-input').value='USD';this.parentElement.querySelectorAll('.segmented-item').forEach(b=>b.classList.remove('segmented-item-active'));this.classList.add('segmented-item-active');">{{ __('messages.usd') }}</button>
                    <input type="hidden" name="currency" id="currency-input" value="{{ $selectedCurrency }}">
                </div>
            </div>
            <div class="col-span-2 sm:col-span-1 flex items-end">
                <button type="submit" class="btn-primary w-full py-3.5"><x-icon name="check" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    {{-- Net profit hero --}}
    <div class="card relative overflow-hidden p-4 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-36 h-36 rounded-full {{ $netPositive ? 'bg-primary-500/[0.07] dark:bg-primary-400/[0.08]' : 'bg-danger-500/[0.07] dark:bg-danger-400/[0.08]' }}"></div>
        <div class="relative">
            <div class="flex items-center justify-between gap-3 mb-2">
                <span class="metric-label">{{ $netPositive ? __('messages.net_profit') : __('messages.loss') }}</span>
                <span class="badge {{ $netPositive ? 'badge-success' : 'badge-danger' }}" dir="ltr">{{ ($netPositive ? '+' : '') . number_format((float) $netProfit, 2) }}</span>
            </div>
            <p class="text-3xl font-extrabold tabular-nums tracking-tight {{ $netClass }}" dir="ltr">
                {{ number_format((float) $netProfit, 2) }} <span class="text-sm font-bold text-ink-400 dark:text-ink-500">{{ $currencyLabel }}</span>
            </p>
            @if($netMarginPercent !== null)
                <p class="mt-2.5 text-[11px] font-medium text-ink-500 dark:text-ink-400">
                    {{ __('messages.net_margin') }}: <span class="font-bold tabular-nums {{ $netClass }}">{{ number_format((float) $netMarginPercent, 1) }}%</span>
                </p>
            @endif
        </div>
    </div>

    {{-- Income --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-primary-600"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.income_section') }}</h3>
            </div>
        </div>
        <div class="px-4 py-3.5">
            <div class="flex items-center justify-between tabular-nums">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-primary-50 dark:bg-primary-900/25 text-primary-600 dark:text-primary-400 flex items-center justify-center flex-shrink-0">
                        <x-icon name="arrow-trending-up" class="w-4 h-4" strokeWidth="1.8"/>
                    </div>
                    <span class="text-sm text-ink-700 dark:text-ink-300 truncate">{{ __('messages.total_sales') }}</span>
                </div>
                <span class="text-sm font-bold text-primary-600 dark:text-primary-400 flex-shrink-0 ms-3" dir="ltr">{{ number_format((float) $totalRevenue, 2) }} {{ $currencyLabel }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between px-4 py-3.5 border-t border-ink-100 dark:border-ink-700/30 bg-primary-50/50 dark:bg-primary-900/5 tabular-nums">
            <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.gross_profit') }}</span>
            <span class="text-sm font-extrabold text-primary-700 dark:text-primary-300" dir="ltr">{{ number_format((float) $grossProfit, 2) }} {{ $currencyLabel }}</span>
        </div>
    </div>

    {{-- Product Profit / Loss (per-unit margin vs weighted average purchase cost) --}}
    @php
        $unitPositive = bccomp((string) $unitTotals['profit'], '0', 2) >= 0;
        $hasUnitActivity = $unitTotals['qty'] > 0
            || bccomp((string) ($unitTotals['returned_revenue'] ?? '0.00'), '0.00', 2) !== 0;
    @endphp
    @if($hasUnitActivity)
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.17s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-4 rounded-full bg-primary-400"></div>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.unit_profit_loss') }}</h3>
                </div>
            </div>
            <div class="px-4 py-3.5 flex items-center justify-between gap-3 tabular-nums">
                <span class="text-sm font-bold {{ $unitPositive ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                    {{ $unitPositive ? __('messages.profit') : __('messages.loss') }}
                </span>
                <span class="text-sm font-extrabold {{ $unitPositive ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}" dir="ltr">
                    {{ ($unitPositive ? '+' : '-') . number_format(abs((float) $unitTotals['profit']), 2) }} {{ $currencyLabel }}
                </span>
            </div>
            <div class="px-4 pb-3 text-[10px] font-medium text-ink-400 dark:text-ink-500 tabular-nums" dir="ltr">
                {{ __('messages.revenue') }} {{ number_format((float) $unitTotals['revenue'], 2) }}
                &middot; {{ __('messages.cost') }} {{ number_format((float) $unitTotals['cost'], 2) }}
                @if(bccomp((string) ($unitTotals['returned_revenue'] ?? '0.00'), '0.00', 2) !== 0)
                    &middot; {{ __('messages.returned') }} {{ number_format((float) $unitTotals['returned_revenue'], 2) }}
                @endif
            </div>
            @if($unitTotals['missing_cost_lines'] > 0)
                <div class="mx-4 mb-3 rounded-xl bg-accent-50 dark:bg-accent-900/20 border border-accent-100 dark:border-accent-900/40 px-3 py-2 text-[11px] font-semibold text-accent-700 dark:text-accent-300">
                    {{ $unitTotals['missing_cost_lines'] }} × {{ __('messages.no_cost_data') }} — {{ strtolower($selectedCurrency) === 'usd' ? '$' : '' }}{{ __('messages.cost') }} = 0
                </div>
            @endif

            @if($unitProfitData['by_product']->isNotEmpty())
                <div class="px-4 pb-1 pt-1 border-t border-ink-100 dark:border-ink-700/30">
                    <span class="section-header-title">{{ __('messages.per_product') }}</span>
                </div>
                <div class="divide-y divide-ink-100 dark:border-ink-700/25 border-t border-ink-100 dark:border-ink-700/30">
                    @foreach ($unitProfitData['by_product']->filter(fn ($row) => $row['currency'] === $selectedCurrency) as $row)
                        @php
                            $rowPos = bccomp($row['profit'], '0', 2) >= 0;
                        @endphp
                        <div class="flex items-center justify-between gap-3 px-4 py-3 tabular-nums">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $row['product_name'] }}</p>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5" dir="ltr">
                                    {{ __('messages.qty_sold') }} {{ $row['qty'] }}
                                    &middot; {{ __('messages.avg_cost') }} {{ number_format((float) $row['avg_cost'], 2) }}
                                    &middot; {{ __('messages.avg_sell') }} {{ number_format((float) $row['avg_sell'], 2) }}
                                </p>
                            </div>
                            <span class="text-sm font-bold flex-shrink-0 ms-3 {{ $rowPos ? 'text-primary-600 dark:text-primary-400' : 'text-danger-500' }}" dir="ltr">
                                {{ ($rowPos ? '+' : '-') . number_format(abs((float) $row['profit']), 2) }} {{ $currencyLabel }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($unitProfitData['sale_lines']->isNotEmpty())
                <div class="px-4 pb-1 pt-2">
                    <span class="section-header-title">{{ __('messages.per_sale') }}</span>
                </div>
                <div class="divide-y divide-ink-100 dark:border-ink-700/25 border-t border-ink-100 dark:border-ink-700/30">
                    @foreach ($unitProfitData['sale_lines'] as $line)
                        @php
                            $linePos = $line['profit'] !== null && bccomp($line['profit'], '0', 2) >= 0;
                        @endphp
                        <a href="{{ route('orders.show', $line['order_id']) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-ink-50 dark:hover:bg-white/[0.03] transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $line['product_name'] }}</p>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5" dir="ltr">
                                    #{{ $line['order_id'] }} &middot; {{ $line['date']->format('Y-m-d') }} &middot; ×{{ $line['qty'] }}
                                    @if($line['cost_unit'] !== null)
                                        &middot; {{ number_format((float) $line['cost_unit'], 2) }} → {{ number_format((float) $line['sell_unit'], 2) }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-xs font-bold flex-shrink-0 ms-3 {{ $line['profit'] === null ? 'text-ink-400 dark:text-ink-500' : ($linePos ? 'text-primary-600 dark:text-primary-400' : 'text-danger-500') }}" dir="ltr">{{ $line['profit'] === null ? '—' : (($linePos ? '+' : '-') . number_format(abs((float) $line['profit']), 2)) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Expenses --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-danger-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.expenses_section') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/25">
            @foreach ([
                ['icon' => 'shopping-cart', 'label' => __('messages.cogs'), 'amount' => $totalCOGS, 'landed' => $landedCosts],
                ['icon' => 'banknotes', 'label' => __('messages.cash_expenses'), 'amount' => $cashExpenses],
                ['icon' => 'users', 'label' => __('messages.salaries'), 'amount' => $salaryExpenses],
                ['icon' => 'credit-card', 'label' => __('messages.expenses'), 'amount' => $operatingExpenses],
            ] as $row)
                @php
                    $share = $shareOfTotal($row['amount']);
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
                                @if(($row['landed'] ?? 0) > 0)
                                    <p class="text-[10px] font-semibold text-accent-600 dark:text-accent-400 tabular-nums mt-0.5">{{ __('messages.incl_landed_costs') }}: {{ number_format((float) $row['landed'], 2) }}</p>
                                @endif
                            </div>
                        </div>
                        <span class="text-sm font-bold text-danger-600 dark:text-danger-400 flex-shrink-0 ms-3" dir="ltr">-{{ number_format((float) $row['amount'], 2) }} {{ $currencyLabel }}</span>
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
            <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.total_expenses') }}</span>
            <span class="text-sm font-extrabold text-danger-700 dark:text-danger-300" dir="ltr">-{{ number_format((float) $totalExpenses, 2) }} {{ $currencyLabel }}</span>
        </div>
    </div>
@endsection
