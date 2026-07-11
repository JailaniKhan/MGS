@extends('layouts.app')

@section('content')
<div class="page-enter">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.spend_breakdown') }}</h2>
    </div>

    <!-- Period Filter -->
    <div class="card p-3 mb-4">
        <div class="flex gap-2 mb-3">
            <a href="{{ route('spend-breakdown.index', ['period' => 'week']) }}"
               class="badge whitespace-nowrap {{ $period === 'week' ? 'badge-success' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                {{ __('messages.weekly') }}
            </a>
            <a href="{{ route('spend-breakdown.index', ['period' => 'month']) }}"
               class="badge whitespace-nowrap {{ $period === 'month' ? 'badge-success' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                {{ __('messages.monthly') }}
            </a>
            <a href="{{ route('spend-breakdown.index', ['period' => 'year']) }}"
               class="badge whitespace-nowrap {{ $period === 'year' ? 'badge-success' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                {{ __('messages.yearly') }}
            </a>
        </div>
        <form method="GET" action="{{ route('spend-breakdown.index') }}" class="grid grid-cols-2 gap-2">
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input text-xs">
            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input text-xs">
            <button type="submit" class="btn-primary btn-sm col-span-2">{{ __('messages.filter') }}</button>
        </form>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-2 gap-2 mb-4">
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_expenses') }}</span>
            <span class="metric-value text-red-500">{{ number_format($totalExpenses) }}</span>
            <span class="text-[9px] text-gray-400">{{ __('messages.afn') }}</span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.categories') }}</span>
            <span class="metric-value">{{ $byCategory->count() }}</span>
        </div>
    </div>

    <!-- Expense by Category -->
    <div class="card overflow-hidden mb-4">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-red-400"></div>
            <span class="section-header-title">{{ __('messages.by_category') }}</span>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700/30">
            @forelse ($byCategory as $cat)
                <div class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $cat['category'] }}</div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $cat['count'] }} {{ __('messages.transactions') }}</div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <div class="text-sm font-bold text-red-500">{{ number_format($cat['total']) }}</div>
                        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 mt-1" style="width: 80px;">
                            <div class="bg-red-400 h-1.5 rounded-full" style="width: {{ $totalExpenses > 0 ? ($cat['total'] / $totalExpenses * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('messages.no_expenses') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Daily Trend Chart -->
    @if($dailyTrend->count() > 0)
        <div class="chart-container mb-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-red-400"></div>
                <h3 class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('messages.daily_trend') }}</h3>
            </div>
            <div class="relative" style="height: 180px;">
                <canvas id="dailyTrendChart"></canvas>
            </div>
        </div>
    @endif

    <div class="mt-4 text-center">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            {{ __('messages.back') }}
        </a>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($dailyTrend->count() > 0)
    const ctx = document.getElementById('dailyTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: @json($dailyTrend->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))->values()),
            datasets: [{
                label: '{{ __('messages.expenses') }}',
                data: @json($dailyTrend->pluck('total')->values()),
                backgroundColor: 'rgba(239, 68, 68, 0.5)',
                borderColor: '#ef4444',
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 9 } } },
                x: { grid: { display: false }, ticks: { font: { size: 9 } } }
            }
        }
    });
    @endif
});
</script>
@endpush
@endsection
