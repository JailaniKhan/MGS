@extends('layouts.app')

@section('content')
    <!-- Brand hero: net balance -->
    <div class="hero-aurora rounded-[1.5rem] p-5 mb-6 text-white relative overflow-hidden">
        <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="absolute -right-2 top-10 w-16 h-16 rounded-full bg-white/5"></div>
        <p class="relative text-[11px] font-semibold uppercase tracking-[0.14em] text-white/80">{{ __('messages.net_balance') }}</p>
        <div class="relative flex items-end gap-6 mt-2">
            <div>
                <p class="text-3xl font-extrabold tabular-nums tracking-tight text-glow-soft" data-count="{{ $totalRevenueAFN - $totalExpenseAFN }}">{{ number_format($totalRevenueAFN - $totalExpenseAFN) }}</p>
                <p class="text-[11px] font-semibold text-white/70 mt-0.5">{{ __('messages.afn') }}</p>
            </div>
            <div>
                <p class="text-3xl font-extrabold tabular-nums tracking-tight text-glow-soft" data-count="{{ $totalRevenueUSD - $totalExpenseUSD }}">{{ number_format($totalRevenueUSD - $totalExpenseUSD) }}</p>
                <p class="text-[11px] font-semibold text-white/70 mt-0.5">{{ __('messages.usd') }}</p>
            </div>
        </div>
        <div class="relative mt-4 space-y-1.5 text-[11px] font-medium text-white/90">
            <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>{{ __('messages.revenue') }}: {{ number_format($totalRevenueAFN) }} {{ __('messages.afn') }} &middot; {{ number_format($totalRevenueUSD) }} {{ __('messages.usd') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-white/50"></span>{{ __('messages.expenses') }}: {{ number_format($totalExpenseAFN) }} {{ __('messages.afn') }} &middot; {{ number_format($totalExpenseUSD) }} {{ __('messages.usd') }}</span>
        </div>
    </div>

    <!-- Quick Action Cards (DigiKhata-style) -->
    <div class="grid-responsive-2 mb-6 stagger">
        <a href="{{ route('orders.create') }}" class="action-card spring-press">
            <span class="spot-wash"></span>
            <x-icon name="plus" class="w-7 h-7 relative"/>
            <span class="text-[10px] font-bold leading-tight relative">{{ __('messages.new_order') }}</span>
        </a>
        <a href="{{ route('purchases.create') }}" class="action-card spring-press">
            <span class="spot-wash"></span>
            <x-icon name="shopping-cart" class="w-7 h-7 relative"/>
            <span class="text-[10px] font-bold leading-tight relative">{{ __('messages.new_purchase') }}</span>
        </a>
        <a href="{{ route('expenses.create') }}" class="action-card spring-press">
            <span class="spot-wash"></span>
            <x-icon name="currency-dollar" class="w-7 h-7 relative"/>
            <span class="text-[10px] font-bold leading-tight relative">{{ __('messages.new_expense') }}</span>
        </a>
        <a href="{{ route('people.index') }}" class="action-card spring-press">
            <span class="spot-wash"></span>
            <x-icon name="users" class="w-7 h-7 relative"/>
            <span class="text-[10px] font-bold leading-tight relative">{{ __('messages.people') }}</span>
        </a>
    </div>

    <!-- Summary Metrics — revenue / expense detail is in the
         hero above; these two are the actionable counters -->
    <div class="grid grid-cols-2 gap-3 mb-6 stagger">
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.pending') }}</span>
            <span class="metric-value text-accent-500" data-count="{{ $pendingOrders + $pendingPayments }}">{{ $pendingOrders + $pendingPayments }}</span>
            <span class="text-[10px] text-ink-400">{{ __('messages.documents') }}</span>
        </div>
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.processing') }}</span>
            <span class="metric-value text-secondary-500" data-count="{{ $processingOrders }}">{{ $processingOrders }}</span>
            <span class="text-[10px] text-ink-400">{{ __('messages.orders') }}</span>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 gap-3 mb-5">
        <div class="chart-container card-spot">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-xs font-bold text-ink-800 dark:text-ink-200">{{ __('messages.weekly_revenue') }}</h3>
                <div class="ml-auto flex p-0.5 rounded-lg bg-ink-100 dark:bg-white/[0.05] border border-ink-200/70 dark:border-white/[0.06]" data-chart-toggle>
                    <button type="button" class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-white dark:bg-white/10 text-ink-900 dark:text-white shadow-sm" data-currency="afn">{{ __('messages.afn') }}</button>
                    <button type="button" class="px-2.5 py-1 rounded-md text-[10px] font-bold text-ink-500 dark:text-ink-400" data-currency="usd">{{ __('messages.usd') }}</button>
                </div>
            </div>
            <div class="relative" style="height: 160px;">
                <canvas id="weeklyRevenueChart"></canvas>
            </div>
        </div>
        <div class="chart-container card-spot">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-danger-400"></div>
                <h3 class="text-xs font-bold text-ink-800 dark:text-ink-200">{{ __('messages.weekly_expenses') }}</h3>
                <div class="ml-auto flex p-0.5 rounded-lg bg-ink-100 dark:bg-white/[0.05] border border-ink-200/70 dark:border-white/[0.06]" data-chart-toggle>
                    <button type="button" class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-white dark:bg-white/10 text-ink-900 dark:text-white shadow-sm" data-currency="afn">{{ __('messages.afn') }}</button>
                    <button type="button" class="px-2.5 py-1 rounded-md text-[10px] font-bold text-ink-500 dark:text-ink-400" data-currency="usd">{{ __('messages.usd') }}</button>
                </div>
            </div>
            <div class="relative" style="height: 160px;">
                <canvas id="weeklyExpenseChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    @if ($lowStockProducts > 0)
        <a href="{{ route('inventory.index') }}" class="flex items-center gap-3 p-3.5 mb-4 rounded-xl bg-danger-50 dark:bg-danger-900/10 border border-danger-200 dark:border-danger-800/30">
            <div class="w-8 h-8 rounded-lg bg-danger-100 dark:bg-danger-900/30 flex items-center justify-center flex-shrink-0">
                <x-icon name="exclamation-triangle" class="w-4 h-4 text-danger-500"/>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-danger-800 dark:text-danger-300">
                    <span class="text-danger-600 dark:text-danger-400">{{ $lowStockProducts }}</span> {{ __('messages.low_stock_products') }}
                </p>
                <p class="text-[11px] text-danger-600 dark:text-danger-400">{{ __('messages.take_action') }}</p>
            </div>
            <x-icon name="chevron-right" class="w-4 h-4 text-danger-400 flex-shrink-0" strokeWidth="2"/>
        </a>
    @endif

    <!-- Recent Orders -->
    <div class="card overflow-hidden mb-3">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-primary-500"></div>
            <span class="section-header-title">{{ __('messages.recent_orders') }}</span>
            <a href="{{ route('orders.index') }}" class="ml-auto text-[10px] font-bold text-primary-600 dark:text-primary-400">
                {{ __('messages.view_all') }}
            </a>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($recentOrders as $order)
                <a href="{{ route('orders.show', $order) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-secondary-100 dark:bg-secondary-900/30 flex items-center justify-center flex-shrink-0">
                            <x-icon name="user" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $order->party?->name ?? __('messages.unknown') }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400"><bdi>{{ local_date($order->created_at, 'd M') }}</bdi></div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100"><x-money :amount="$order->total_amount" :currency="$order->currency" symbol-class="text-[10px] font-medium text-ink-500"/></div>
                        <span class="inline-flex items-center gap-1 badge
                            @if($order->display_status === 'paid' || $order->display_status === 'completed') badge-success
                            @elseif($order->display_status === 'processing') badge-info
                            @elseif($order->display_status === 'cancelled') badge-danger
                            @else badge-warning @endif">
                            @switch($order->display_status)
                                @case('paid') {{ __('messages.paid') }} @break
                                @case('completed') {{ __('messages.completed') }} @break
                                @case('processing') {{ __('messages.processing') }} @break
                                @case('cancelled') {{ __('messages.cancelled') }} @break
                                @default {{ __('messages.pending') }}
                            @endswitch
                        </span>
                    </div>
                </a>
            @empty
                <x-empty-state description="{{ __('messages.no_orders') }}">
                    <x-icon name="inbox" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
    </div>

    <!-- Top Debtors -->
    <div class="card overflow-hidden mb-3">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-primary-500"></div>
            <span class="section-header-title">{{ __('messages.top_debtors_creditors') }}</span>
            <a href="{{ route('ledger.index') }}" class="ml-auto text-[10px] font-bold text-primary-600 dark:text-primary-400">
                {{ __('messages.view_all') }}
            </a>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($topDebtors as $debtor)
                @php
                    $overall = $debtor->pending_afn + $debtor->pending_usd * $rate;
                    $isDebtor = $overall >= 0;
                @endphp
                <a href="{{ route('ledger.show', [$debtor->type, $debtor->id]) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-8 h-8 rounded-lg {{ $isDebtor ? 'bg-danger-100 dark:bg-danger-900/30' : 'bg-accent-100 dark:bg-accent-900/30' }} flex items-center justify-center flex-shrink-0">
                            <x-icon name="user" class="w-4 h-4 {{ $isDebtor ? 'text-danger-600 dark:text-danger-400' : 'text-accent-600 dark:text-accent-400' }}"/>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $debtor->name }}</div>
                                <span class="badge {{ $isDebtor ? 'badge-danger' : 'badge-warning' }} flex-shrink-0">{{ __($isDebtor ? 'messages.owes_you' : 'messages.you_owe') }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-ink-500 dark:text-ink-400 mt-0.5">
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-ink-100 dark:bg-ink-800 text-ink-500 dark:text-ink-400 capitalize flex-shrink-0">{{ $debtor->type }}</span>
                                @if ($debtor->pending_afn != 0)
                                    <span class="font-semibold tabular-nums {{ $debtor->pending_afn > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-accent-600 dark:text-accent-400' }}">
                                        {{ $debtor->pending_afn > 0 ? '+' : '-' }}{{ number_format(abs($debtor->pending_afn)) }} {{ __('messages.afn') }}
                                    </span>
                                @endif
                                @if ($debtor->pending_usd != 0)
                                    <span class="font-semibold tabular-nums {{ $debtor->pending_usd > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-accent-600 dark:text-accent-400' }}">
                                        {{ $debtor->pending_usd > 0 ? '+' : '-' }}{{ number_format(abs($debtor->pending_usd)) }}$
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <x-empty-state description="{{ __('messages.all_settled') }}">
                    <x-icon name="check-circle" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
    </div>

    <!-- Recent Purchases -->
    <div class="card overflow-hidden mb-3">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-primary-500"></div>
            <span class="section-header-title">{{ __('messages.recent_purchases') }}</span>
            <a href="{{ route('purchases.index') }}" class="ml-auto text-[10px] font-bold text-primary-600 dark:text-primary-400">
                {{ __('messages.view_all') }}
            </a>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($recentPurchases as $purchase)
                <a href="{{ route('purchases.show', $purchase) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-accent-100 dark:bg-accent-900/30 flex items-center justify-center flex-shrink-0">
                            <x-icon name="shopping-bag" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400"><bdi>{{ local_date($purchase->created_at, 'd M') }}</bdi></div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100"><x-money :amount="$purchase->total_amount" :currency="$purchase->currency" symbol-class="text-[10px] font-medium text-ink-500"/></div>
                        <span class="inline-flex items-center gap-1 badge
                            @if($purchase->display_status === 'paid' || $purchase->display_status === 'completed') badge-success
                            @elseif($purchase->display_status === 'processing') badge-info
                            @elseif($purchase->display_status === 'cancelled') badge-danger
                            @elseif($purchase->display_status === 'partial') badge-info
                            @else badge-warning @endif">
                            @switch($purchase->display_status)
                                @case('paid') {{ __('messages.paid') }} @break
                                @case('completed') {{ __('messages.completed') }} @break
                                @case('processing') {{ __('messages.processing') }} @break
                                @case('cancelled') {{ __('messages.cancelled') }} @break
                                @case('partial') {{ __('messages.partially_paid') }} @break
                                @default {{ __('messages.pending') }}
                            @endswitch
                        </span>
                    </div>
                </a>
            @empty
                <x-empty-state description="{{ __('messages.no_purchases') }}">
                    <x-icon name="inbox" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
    </div>

    <!-- Recent Reminders -->
    <div class="card overflow-hidden mb-3">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-primary-500"></div>
            <span class="section-header-title">{{ __('messages.recent_reminders') }}</span>
            <a href="{{ route('reminders.history') }}" class="ml-auto text-[10px] font-bold text-primary-600 dark:text-primary-400">
                {{ __('messages.view_all') }}
            </a>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($recentReminders as $reminder)
                <div class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg
                            @if($reminder->status === 'sent') bg-primary-100 dark:bg-primary-900/30
                            @elseif($reminder->status === 'failed') bg-danger-100 dark:bg-danger-900/30
                            @else bg-accent-100 dark:bg-accent-900/30 @endif
                            flex items-center justify-center flex-shrink-0">
                            <x-icon name="bell" class="w-4 h-4
                                @if($reminder->status === 'sent') text-primary-600 dark:text-primary-400
                                @elseif($reminder->status === 'failed') text-danger-600 dark:text-danger-400
                                @else text-accent-600 dark:text-accent-400 @endif"/>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $reminder->remindable?->name ?? __('messages.deleted') }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $reminder->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    <span class="badge
                        @if($reminder->status === 'sent') badge-success
                        @elseif($reminder->status === 'failed') badge-danger
                        @else badge-warning @endif">
                        {{ __("messages.{$reminder->status}") }}
                    </span>
                </div>
            @empty
                <x-empty-state description="{{ __('messages.no_reminders') }}" />
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
@vite('resources/js/charts.js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    MGSCharts.ready(function (Chart) {
        const C = MGSCharts.colors;
        MGSCharts.applyDefaults();

        const commonOptions = MGSCharts.baseOptions();

        const revenueCtx = document.getElementById('weeklyRevenueChart').getContext('2d');
        const revenueGradient = revenueCtx.createLinearGradient(0, 0, 0, 160);
        revenueGradient.addColorStop(0, MGSCharts.alpha(C.brand, 0.22));
        revenueGradient.addColorStop(1, MGSCharts.alpha(C.brand, 0));

        const usdGradient = revenueCtx.createLinearGradient(0, 0, 0, 160);
        usdGradient.addColorStop(0, MGSCharts.alpha(C.secondary, 0.18));
        usdGradient.addColorStop(1, MGSCharts.alpha(C.secondary, 0));

        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: @json($weeklyRevenue['labels']),
                datasets: [
                    {
                        label: '{{ __('messages.revenue') }} ({{ __('messages.afn') }})',
                        data: @json($weeklyRevenue['data_afn']),
                        currency: 'afn',
                        backgroundColor: revenueGradient,
                        borderColor: C.brand,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: C.brand,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 7
                    },
                    {
                        label: '{{ __('messages.revenue') }} ({{ __('messages.usd') }})',
                        data: @json($weeklyRevenue['data_usd']),
                        currency: 'usd',
                        hidden: true,
                        backgroundColor: usdGradient,
                        borderColor: C.secondary,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: false,
                        pointBackgroundColor: C.secondary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 7
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                boxWidth: 8,
                                boxHeight: 8,
                                usePointStyle: true,
                                font: { size: 10 }
                            }
                        },
                        tooltip: {
                            ...commonOptions.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            }
        });

        const expenseCtx = document.getElementById('weeklyExpenseChart').getContext('2d');

        new Chart(expenseCtx, {
            type: 'bar',
            data: {
                labels: @json($weeklyExpenses['labels']),
                datasets: [
                    {
                        label: '{{ __('messages.expenses') }} ({{ __('messages.afn') }})',
                        data: @json($weeklyExpenses['data_afn']),
                        currency: 'afn',
                        backgroundColor: MGSCharts.alpha(C.danger, 0.6),
                        borderColor: C.danger,
                        borderWidth: 0,
                        borderRadius: 3
                    },
                    {
                        label: '{{ __('messages.expenses') }} ({{ __('messages.usd') }})',
                        data: @json($weeklyExpenses['data_usd']),
                        currency: 'usd',
                        hidden: true,
                        backgroundColor: MGSCharts.alpha(C.secondary, 0.6),
                        borderColor: C.secondary,
                        borderWidth: 0,
                        borderRadius: 3
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                boxWidth: 8,
                                boxHeight: 8,
                                usePointStyle: true,
                                font: { size: 10 }
                            }
                        },
                        tooltip: {
                            ...commonOptions.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            }
        });

        // Currency toggle — AFN and USD differ by ~70x, so both
        // series on one axis flattens the smaller one. Each
        // chart shows a single currency at a time.
        document.querySelectorAll('[data-chart-toggle]').forEach(function (group) {
            var canvas = group.closest('.chart-container').querySelector('canvas');
            group.querySelectorAll('button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    group.querySelectorAll('button').forEach(function (b) {
                        b.className = 'px-2.5 py-1 rounded-md text-[10px] font-bold text-ink-500 dark:text-ink-400';
                    });
                    btn.className = 'px-2.5 py-1 rounded-md text-[10px] font-bold bg-white dark:bg-white/10 text-ink-900 dark:text-white shadow-sm';
                    var chart = window.Chart && Chart.getChart ? Chart.getChart(canvas) : null;
                    if (!chart) return;
                    chart.data.datasets.forEach(function (ds) {
                        if (ds.currency) ds.hidden = ds.currency !== btn.dataset.currency;
                    });
                    chart.update();
                });
            });
        });
    });
});
</script>
@endpush

@push('scripts')
    <script>
        // "Synchronized" feel: when the app returns to the foreground and the
        // page has been idle for a while, pull fresh data instead of showing
        // the stale snapshot. Never reloads while the user is filling a form
        // or typing, and never during the first minute of viewing.
        (function () {
            var lastActivity = Date.now();

            function busy() {
                var el = document.activeElement;
                return !!el && /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName);
            }

            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState !== 'visible') {
                    return;
                }
                if (Date.now() - lastActivity > 60000 && !busy()) {
                    window.location.reload();
                }
            });

            ['click', 'touchstart', 'keydown', 'submit'].forEach(function (name) {
                document.addEventListener(name, function () {
                    lastActivity = Date.now();
                }, { passive: true });
            });
        })();
    </script>
@endpush
