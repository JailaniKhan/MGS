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

    <!-- Summary Metrics -->
    <div class="grid-responsive-3 mb-6 stagger">
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.revenue') }} &middot; {{ __('messages.afn') }}</span>
            <span class="metric-value text-primary-600 dark:text-primary-400" data-count="{{ $totalRevenueAFN }}">{{ number_format($totalRevenueAFN) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.afn') }}</span>
        </div>
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.revenue') }} &middot; {{ __('messages.usd') }}</span>
            <span class="metric-value text-primary-600 dark:text-primary-400" data-count="{{ $totalRevenueUSD }}">{{ number_format($totalRevenueUSD) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.usd') }}</span>
        </div>
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.expenses') }} &middot; {{ __('messages.afn') }}</span>
            <span class="metric-value text-danger-500" data-count="{{ $totalExpenseAFN }}">{{ number_format($totalExpenseAFN) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.afn') }}</span>
        </div>
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.expenses') }} &middot; {{ __('messages.usd') }}</span>
            <span class="metric-value text-danger-500" data-count="{{ $totalExpenseUSD }}">{{ number_format($totalExpenseUSD) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.usd') }}</span>
        </div>
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.pending') }}</span>
            <span class="metric-value text-accent-500" data-count="{{ $pendingOrders + $pendingPayments }}">{{ $pendingOrders + $pendingPayments }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.documents') }}</span>
        </div>
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.processing') }}</span>
            <span class="metric-value text-secondary-500" data-count="{{ $processingOrders }}">{{ $processingOrders }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.orders') }}</span>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 gap-3 mb-5">
        <div class="chart-container card-spot">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-xs font-bold text-ink-800 dark:text-ink-200">{{ __('messages.weekly_revenue') }}</h3>
            </div>
            <div class="relative" style="height: 160px;">
                <canvas id="weeklyRevenueChart"></canvas>
            </div>
        </div>
        <div class="chart-container card-spot">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-danger-400"></div>
                <h3 class="text-xs font-bold text-ink-800 dark:text-ink-200">{{ __('messages.weekly_expenses') }}</h3>
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
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $order->created_at->format('d M') }}</div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100">{{ number_format($order->total_amount) }}</div>
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
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg {{ $isDebtor ? 'bg-danger-100 dark:bg-danger-900/30' : 'bg-accent-100 dark:bg-accent-900/30' }} flex items-center justify-center flex-shrink-0">
                            <x-icon name="user" class="w-4 h-4 {{ $isDebtor ? 'text-danger-600 dark:text-danger-400' : 'text-accent-600 dark:text-accent-400' }}"/>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $debtor->name }}</div>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-ink-100 dark:bg-ink-800 text-ink-500 dark:text-ink-400 capitalize">{{ $debtor->type }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-ink-500 dark:text-ink-400">
                                @if ($debtor->pending_afn != 0)
                                    <span class="font-semibold {{ $debtor->pending_afn > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-accent-600 dark:text-accent-400' }}">
                                        {{ $debtor->pending_afn > 0 ? '+' : '-' }}{{ number_format(abs($debtor->pending_afn)) }} {{ __('messages.afn') }}
                                    </span>
                                @endif
                                @if ($debtor->pending_usd != 0)
                                    <span class="font-semibold {{ $debtor->pending_usd > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-accent-600 dark:text-accent-400' }}">
                                        {{ $debtor->pending_usd > 0 ? '+' : '-' }}{{ number_format(abs($debtor->pending_usd)) }}$
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 ms-3">
                        <span class="badge {{ $isDebtor ? 'badge-danger' : 'badge-warning' }}">{{ __($isDebtor ? 'messages.owes_you' : 'messages.you_owe') }}</span>
                        <x-icon name="chevron-right" class="w-4 h-4 text-ink-400 flex-shrink-0" strokeWidth="2"/>
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
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $purchase->created_at->format('d M') }}</div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100">{{ number_format($purchase->total_amount) }}</div>
                        <span class="inline-flex items-center gap-1 badge
                            @if($purchase->display_status === 'paid' || $purchase->display_status === 'completed') badge-success
                            @elseif($purchase->display_status === 'processing') badge-info
                            @elseif($purchase->display_status === 'cancelled') badge-danger
                            @else badge-warning @endif">
                            @switch($purchase->display_status)
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
    (async function initCharts() {
        // Wait for window.Chart (module scripts are deferred)
        let tries = 0;
        while (!window.Chart && tries < 100) { await new Promise(r => setTimeout(r, 50)); tries++; }
        if (!window.Chart) return;
        Chart.defaults.font.family = "'Plus Jakarta Sans', 'Vazirmatn', sans-serif";
        Chart.defaults.font.size = 10;
        Chart.defaults.color = '#9ca3af';

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1f2022',
                    titleColor: '#fff',
                    bodyColor: '#e4e4e7',
                    padding: 10,
                    cornerRadius: 10,
                    titleFont: { size: 11, weight: '600' },
                    bodyFont: { size: 10 },
                    borderColor: 'rgba(255,255,255,0.08)',
                    borderWidth: 1,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.04)',
                        drawBorder: false
                    },
                    ticks: {
                        font: { size: 9 },
                        padding: 6,
                        maxTicksLimit: 4
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 9 },
                        maxTicksLimit: 7
                    }
                }
            }
        };

        const revenueCtx = document.getElementById('weeklyRevenueChart').getContext('2d');
        const revenueGradient = revenueCtx.createLinearGradient(0, 0, 0, 160);
        revenueGradient.addColorStop(0, 'rgba(16, 174, 100, 0.22)');
        revenueGradient.addColorStop(1, 'rgba(16, 174, 100, 0)');

        const usdGradient = revenueCtx.createLinearGradient(0, 0, 0, 160);
        usdGradient.addColorStop(0, 'rgba(18, 131, 233, 0.18)');
        usdGradient.addColorStop(1, 'rgba(18, 131, 233, 0)');

        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: @json($weeklyRevenue['labels']),
                datasets: [
                    {
                        label: '{{ __('messages.revenue') }} ({{ __('messages.afn') }})',
                        data: @json($weeklyRevenue['data_afn']),
                        backgroundColor: revenueGradient,
                        borderColor: '#10ae64',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#10ae64',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: '{{ __('messages.revenue') }} ({{ __('messages.usd') }})',
                        data: @json($weeklyRevenue['data_usd']),
                        backgroundColor: usdGradient,
                        borderColor: '#1283e9',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: false,
                        pointBackgroundColor: '#1283e9',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
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
                            font: { size: 9 }
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
                        backgroundColor: 'rgba(230, 85, 85, 0.6)',
                        borderColor: '#e65555',
                        borderWidth: 0,
                        borderRadius: 3
                    },
                    {
                        label: '{{ __('messages.expenses') }} ({{ __('messages.usd') }})',
                        data: @json($weeklyExpenses['data_usd']),
                        backgroundColor: 'rgba(18, 131, 233, 0.6)',
                        borderColor: '#1283e9',
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
                            font: { size: 9 }
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
        });
    })();
</script>
@endpush