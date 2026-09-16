@extends('layouts.app')

@section('content')
<div class="page-enter">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-danger-50 dark:bg-danger-900/30 border border-danger-100 dark:border-danger-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="banknotes" class="w-4 h-4 text-danger-600 dark:text-danger-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.spend_breakdown') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate"><bdi>{{ local_date($dateFrom, 'd M Y') }}</bdi> &ndash; <bdi>{{ local_date($dateTo, 'd M Y') }}</bdi></p>
            </div>
        </div>
        <x-back-button href="{{ route('dashboard') }}"/>
    </div>

    {{-- Period filter --}}
    <div class="card p-3 mb-4">
        <div class="flex gap-2 mb-3">
            @foreach ([['week', 'weekly'], ['month', 'monthly'], ['year', 'yearly']] as [$p, $label])
                <a href="{{ route('spend-breakdown.index', ['period' => $p, 'date_from' => $dateFromInput]) }}"
                   class="badge whitespace-nowrap {{ $period === $p ? 'badge-danger' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400 hover:bg-ink-200 dark:hover:bg-ink-700' }}">
                    {{ __('messages.'.$label) }}
                </a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('spend-breakdown.index') }}" class="grid grid-cols-2 gap-2">
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="date" name="date_from" value="{{ $dateFromInput }}" class="form-input text-xs col-span-2">
            <button type="submit" class="btn-primary btn-sm col-span-2">{{ __('messages.filter') }}</button>
        </form>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-2 gap-2 mb-4">
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_expenses') }} ({{ __('messages.afn') }})</span>
            <span class="metric-value text-danger-500">{{ number_format((float) $totalAFN, 2) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.afn') }}</span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_expenses') }} ({{ __('messages.usd') }})</span>
            <span class="metric-value text-danger-500">{{ number_format((float) $totalUSD, 2) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.usd') }}</span>
        </div>
    </div>

    {{-- Expense by category --}}
    @if($linkedCount > 0)
        <div class="card mb-3 px-3.5 py-2.5 page-enter">
            <div class="flex items-center gap-2 text-[11px] font-semibold text-accent-600 dark:text-accent-400">
                <x-icon name="truck" class="w-3.5 h-3.5 flex-shrink-0" strokeWidth="1.8"/>
                {{ $linkedCount }} &times; {{ __('messages.attached_to_purchases') }}
            </div>
        </div>
    @endif
    <div class="card overflow-hidden mb-4">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-danger-400"></div>
            <span class="section-header-title">{{ __('messages.by_category') }}</span>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($byCategory as $cat)
                <div class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded-xl bg-danger-100 dark:bg-danger-900/30 flex items-center justify-center flex-shrink-0">
                            <x-icon name="banknotes" class="w-4 h-4 text-danger-600 dark:text-danger-400"/>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100">{{ $cat['category'] }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $cat['count'] }} {{ __('messages.transactions') }}</div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold tabular-nums text-danger-500">
                            @if($cat['afn'] > 0) {{ number_format((float) $cat['afn'], 2) }} {{ __('messages.afn') }} @endif
                            @if($cat['usd'] > 0)@if($cat['afn'] > 0) · @endif {{ number_format((float) $cat['usd'], 2) }} {{ __('messages.usd') }} @endif
                        </div>
                        @php
                            $primary = $cat['afn'] > 0 ? $cat['afn'] : $cat['usd'];
                            $primaryTotal = $totalAFN > 0 ? $totalAFN : $totalUSD;
                            $progress = $primaryTotal > 0 ? round(((float) $primary / (float) $primaryTotal) * 100) : 0;
                        @endphp
                        <div class="w-20 bg-ink-100 dark:bg-ink-800 rounded-full h-1.5 mt-1 ms-auto">
                            <div class="bg-danger-400 h-1.5 rounded-full" style="width: {{ min(100, $progress) }}%"></div>
                        </div>
                    </div>
                </div>
            @empty
                <x-empty-state title="{{ __('messages.no_expenses') }}">
                    <x-icon name="banknotes" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
    </div>

    {{-- Daily trend chart --}}
    @if($dailyTrend->count() > 0)
        <div class="chart-container mb-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-danger-400"></div>
                <h3 class="text-xs font-bold text-ink-800 dark:text-ink-200">{{ __('messages.daily_trend') }}</h3>
            </div>
            <div class="relative" style="height: 180px;">
                <canvas id="dailyTrendChart"></canvas>
            </div>
        </div>
    @endif

    {{-- Sales profit / loss (per-unit, weighted avg purchase cost) --}}
    @php($plAfn = $profit['totals']['AFN'])
    @php($plUsd = $profit['totals']['USD'])
    @php($hasSales = $plAfn['qty'] > 0 || $plUsd['qty'] > 0)

    @if($hasSales)
        <div class="flex items-center gap-2 mb-3">
            <div class="w-9 h-9 rounded-[0.875rem] bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="arrow-trending-up" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <h3 class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.unit_profit_loss') }}</h3>
        </div>

        {{-- P/L totals per currency --}}
        <div class="grid grid-cols-2 gap-2 mb-4">
            @foreach ([['AFN', $plAfn], ['USD', $plUsd]] as [$cur, $t])
                @if($t['qty'] > 0)
                    @php($pos = bccomp($t['profit'], '0', 2) >= 0)
                    <div class="stat-card">
                        <span class="metric-label">{{ $pos ? __('messages.profit') : __('messages.loss') }} ({{ __('messages.'.strtolower($cur)) }})</span>
                        <span class="metric-value {{ $pos ? 'text-primary-600 dark:text-primary-400' : 'text-danger-500' }}">
                            <x-money :amount="$t['profit']" :currency="$cur" decimals="2" sign="true" symbol-class="text-[10px] font-medium text-ink-400"/>
                        </span>
                        <span class="text-[9px] text-ink-400 tabular-nums">
                            {{ __('messages.revenue') }} <bdi>{{ number_format((float) $t['revenue'], 2) }}</bdi>
                            &middot; {{ __('messages.cost') }} <bdi>{{ number_format((float) $t['cost'], 2) }}</bdi>
                        </span>
                        @if($t['missing_cost_lines'] > 0)
                            <span class="text-[9px] text-accent-600 dark:text-accent-400">{{ $t['missing_cost_lines'] }} {{ __('messages.no_cost_data') }}</span>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Per product --}}
        @if($profit['by_product']->isNotEmpty())
            <div class="card overflow-hidden mb-4">
                <div class="section-header">
                    <div class="w-1 h-4 rounded-full bg-primary-400"></div>
                    <span class="section-header-title">{{ __('messages.per_product') }}</span>
                </div>
                <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
                    @foreach ($profit['by_product'] as $row)
                        @php($pos = bccomp($row['profit'], '0', 2) >= 0)
                        <div class="list-row">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-9 h-9 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                                    <x-icon name="cube" class="w-4 h-4 text-primary-600 dark:text-primary-400"/>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $row['product_name'] }}</div>
                                    <div class="text-[11px] text-ink-500 dark:text-ink-400 tabular-nums">
                                        {{ __('messages.qty_sold') }} <bdi>{{ $row['qty'] }}</bdi>
                                        &middot; {{ __('messages.avg_cost') }} <bdi>{{ number_format((float) $row['avg_cost'], 2) }}</bdi>
                                        &middot; {{ __('messages.avg_sell') }} <bdi>{{ number_format((float) $row['avg_sell'], 2) }}</bdi>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0 ms-3">
                                <div class="text-sm font-bold {{ $pos ? 'text-primary-600 dark:text-primary-400' : 'text-danger-500' }}">
                                    <x-money :amount="$row['profit']" :currency="$row['currency']" decimals="2" sign="true" symbol-class="text-[10px] font-medium text-ink-400"/>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Per sale --}}
        @if($profit['sale_lines']->isNotEmpty())
            <div class="card overflow-hidden mb-4">
                <div class="section-header">
                    <div class="w-1 h-4 rounded-full bg-primary-400"></div>
                    <span class="section-header-title">{{ __('messages.per_sale') }}</span>
                </div>
                <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
                    @foreach ($profit['sale_lines'] as $line)
                        <a href="{{ route('orders.show', $line['order_id']) }}" class="list-row">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-9 h-9 rounded-xl bg-brand/10 dark:bg-brand/20 text-brand flex items-center justify-center flex-shrink-0">
                                    <span class="font-bold text-xs tabular-nums">#{{ $line['order_id'] }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $line['product_name'] }}</div>
                                    <div class="text-[11px] text-ink-500 dark:text-ink-400">
                                        <bdi>{{ local_date($line['date'], 'd M') }}</bdi>
                                        &middot; <span class="tabular-nums">{{ $line['qty'] }}</span> &times; <bdi>{{ number_format((float) $line['sell_unit'], 2) }}</bdi>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0 ms-3">
                                @if($line['has_cost'])
                                    @php($pos = bccomp($line['profit'], '0', 2) >= 0)
                                    <div class="text-sm font-bold {{ $pos ? 'text-primary-600 dark:text-primary-400' : 'text-danger-500' }}">
                                        <x-money :amount="$line['profit']" :currency="$line['currency']" decimals="2" sign="true" symbol-class="text-[10px] font-medium text-ink-400"/>
                                    </div>
                                    <div class="text-[10px] text-ink-400 tabular-nums">{{ __('messages.avg_cost') }} <bdi>{{ number_format((float) $line['cost_unit'], 2) }}</bdi></div>
                                @else
                                    <span class="badge badge-warning">{{ __('messages.no_cost_data') }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>

@push('scripts')
@vite('resources/js/charts.js')
<script>
document.addEventListener('DOMContentLoaded', function () {
MGSCharts.ready(function (Chart) {
    const C = MGSCharts.colors;
    MGSCharts.applyDefaults();
    @if($dailyTrend->count() > 0)
    const ctx = document.getElementById('dailyTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: @json($dailyTrend->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()),
            datasets: [
                {
                    label: '{{ __('messages.afn') }}',
                    data: @json($dailyTrend->pluck('afn')->values()),
                    backgroundColor: MGSCharts.alpha(C.danger, 0.5),
                    borderColor: C.danger,
                    borderWidth: 0,
                    borderRadius: 4
                },
                {
                    label: '{{ __('messages.usd') }}',
                    data: @json($dailyTrend->pluck('usd')->values()),
                    backgroundColor: MGSCharts.alpha(C.secondary, 0.5),
                    borderColor: C.secondary,
                    borderWidth: 0,
                    borderRadius: 4
                }
            ]
        },
        options: MGSCharts.baseOptions({ legend: true })
    });
    @endif
    });
});
</script>
@endpush
@endsection