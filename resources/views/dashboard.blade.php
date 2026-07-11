@extends('layouts.app')

@section('content')
    <!-- Quick Action Cards (DigiKhata-style) -->
    <div class="grid grid-cols-2 gap-3 mb-6">
        <a href="{{ route('orders.create') }}" class="action-card">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            <span class="text-[10px] font-bold leading-tight">{{ __('messages.new_order') }}</span>
        </a>
        <a href="{{ route('purchases.create') }}" class="action-card">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
            </svg>
            <span class="text-[10px] font-bold leading-tight">{{ __('messages.new_purchase') }}</span>
        </a>
        <a href="{{ route('expenses.create') }}" class="action-card">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-[10px] font-bold leading-tight">{{ __('messages.new_expense') }}</span>
        </a>
        <a href="{{ route('customers.index') }}" class="action-card">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
            </svg>
            <span class="text-[10px] font-bold leading-tight">{{ __('messages.customers') }}</span>
        </a>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.revenue') }}</span>
            <span class="metric-value text-primary-600 dark:text-primary-400">{{ number_format($totalRevenueAFN + $totalRevenueUSD * $rate) }}</span>
            <span class="text-[9px] text-gray-400">{{ __('messages.afn') }}</span>
        </div>
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.expenses') }}</span>
            <span class="metric-value text-red-500">{{ number_format($totalExpenseAFN + $totalExpenseUSD * $rate) }}</span>
            <span class="text-[9px] text-gray-400">{{ __('messages.afn') }}</span>
        </div>
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.pending') }}</span>
            <span class="metric-value text-amber-500">{{ $pendingOrders + $pendingPayments }}</span>
            <span class="text-[9px] text-gray-400">{{ __('messages.documents') }}</span>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 gap-3 mb-5">
        <div class="chart-container">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('messages.weekly_revenue') }}</h3>
            </div>
            <div class="relative" style="height: 160px;">
                <canvas id="weeklyRevenueChart"></canvas>
            </div>
        </div>
        <div class="chart-container">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-red-400"></div>
                <h3 class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('messages.weekly_expenses') }}</h3>
            </div>
            <div class="relative" style="height: 160px;">
                <canvas id="weeklyExpenseChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    @if ($lowStockProducts > 0)
        <a href="{{ route('inventory.index') }}" class="flex items-center gap-3 p-3.5 mb-4 rounded-xl bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800/30">
            <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-red-800 dark:text-red-300">
                    <span class="text-red-600 dark:text-red-400">{{ $lowStockProducts }}</span> {{ __('messages.low_stock_products') }}
                </p>
                <p class="text-[11px] text-red-600 dark:text-red-400">{{ __('messages.take_action') }}</p>
            </div>
            <svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
            </svg>
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
        <div class="divide-y divide-gray-100 dark:divide-gray-700/30">
            @forelse ($recentOrders as $order)
                <a href="{{ route('orders.show', $order) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-secondary-100 dark:bg-secondary-900/30 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $order->party?->name }}</div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $order->created_at->format('d M') }}</div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ number_format($order->total_amount) }}</div>
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
                <div class="empty-state">
                    <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.no_orders') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Top Debtors -->
    <div class="card overflow-hidden mb-3">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-primary-500"></div>
            <span class="section-header-title">{{ __('messages.top_debtors') }}</span>
            <a href="{{ route('ledger.index') }}" class="ml-auto text-[10px] font-bold text-primary-600 dark:text-primary-400">
                {{ __('messages.view_all') }}
            </a>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700/30">
            @forelse ($topDebtors as $debtor)
                <a href="{{ route('ledger.show', ['customer', $debtor->id]) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $debtor->name }}</div>
                            <div class="flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                @if ($debtor->pending_afn > 0)
                                    <span class="text-red-600 dark:text-red-400 font-semibold">{{ number_format($debtor->pending_afn) }} {{ __('messages.afn') }}</span>
                                @endif
                                @if ($debtor->pending_usd > 0)
                                    <span class="text-red-600 dark:text-red-400 font-semibold">{{ number_format($debtor->pending_usd) }}$</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>
            @empty
                <div class="empty-state">
                    <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.all_settled') }}</p>
                </div>
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
        <div class="divide-y divide-gray-100 dark:divide-gray-700/30">
            @forelse ($recentPurchases as $purchase)
                <a href="{{ route('purchases.show', $purchase) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }}</div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $purchase->created_at->format('d M') }}</div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ number_format($purchase->total_amount) }}</div>
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
                <div class="empty-state">
                    <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.no_purchases') }}</p>
                </div>
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
        <div class="divide-y divide-gray-100 dark:divide-gray-700/30">
            @forelse ($recentReminders as $reminder)
                <div class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg
                            @if($reminder->status === 'sent') bg-primary-100 dark:bg-primary-900/30
                            @elseif($reminder->status === 'failed') bg-red-100 dark:bg-red-900/30
                            @else bg-amber-100 dark:bg-amber-900/30 @endif
                            flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4
                                @if($reminder->status === 'sent') text-primary-600 dark:text-primary-400
                                @elseif($reminder->status === 'failed') text-red-600 dark:text-red-400
                                @else text-amber-600 dark:text-amber-400 @endif"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $reminder->remindable?->name ?? __('messages.deleted') }}</div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $reminder->created_at->diffForHumans() }}</div>
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
                <div class="empty-state">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.no_reminders') }}</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Chart.defaults.font.family = "'Plus Jakarta Sans', 'Vazirmatn', sans-serif";
        Chart.defaults.font.size = 10;
        Chart.defaults.color = '#9ca3af';

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1E2127',
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
        revenueGradient.addColorStop(0, 'rgba(15, 157, 114, 0.22)');
        revenueGradient.addColorStop(1, 'rgba(15, 157, 114, 0)');

        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: @json($weeklyRevenue['labels']),
                datasets: [{
                    label: '{{ __('messages.revenue') }} ({{ __('messages.afn') }})',
                    data: @json($weeklyRevenue['datasets'][0]['data']),
                    backgroundColor: revenueGradient,
                    borderColor: '#0F9D72',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#0F9D72',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    tooltip: {
                        ...commonOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString() + ' {{ __('messages.afn') }}';
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
                datasets: [{
                    label: '{{ __('messages.expenses') }} ({{ __('messages.afn') }})',
                    data: @json($weeklyExpenses['datasets'][0]['data']),
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.6)',
                        'rgba(239, 68, 68, 0.4)',
                        'rgba(239, 68, 68, 0.3)',
                        'rgba(239, 68, 68, 0.5)',
                        'rgba(239, 68, 68, 0.6)',
                        'rgba(239, 68, 68, 0.3)',
                        'rgba(239, 68, 68, 0.5)'
                    ],
                    borderColor: '#ef4444',
                    borderWidth: 0,
                    borderRadius: 3
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    tooltip: {
                        ...commonOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString() + ' {{ __('messages.afn') }}';
                            }
                        }
                    }
                }
            }
        });
    });
</script>
@endpush