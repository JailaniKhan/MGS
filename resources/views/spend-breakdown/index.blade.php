@extends('layouts.app')

@section('content')
<div class="page-enter">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.spend_breakdown') }}</h2>
    </div>

    <!-- Period Filter -->
    <div class="card p-3 mb-4">
        <div class="flex gap-2 mb-3">
            <a href="{{ route('spend-breakdown.index', ['period' => 'week']) }}"
               class="badge whitespace-nowrap {{ $period === 'week' ? 'badge-success' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
                {{ __('messages.weekly') }}
            </a>
            <a href="{{ route('spend-breakdown.index', ['period' => 'month']) }}"
               class="badge whitespace-nowrap {{ $period === 'month' ? 'badge-success' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
                {{ __('messages.monthly') }}
            </a>
            <a href="{{ route('spend-breakdown.index', ['period' => 'year']) }}"
               class="badge whitespace-nowrap {{ $period === 'year' ? 'badge-success' : 'bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400' }}">
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
            <span class="metric-label">{{ __('messages.total_expenses') }} ({{ __('messages.afn') }})</span>
            <span class="metric-value text-danger-500">{{ number_format($totalAFN) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.afn') }}</span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_expenses') }} ({{ __('messages.usd') }})</span>
            <span class="metric-value text-danger-500">{{ number_format($totalUSD) }}</span>
            <span class="text-[9px] text-ink-400">{{ __('messages.usd') }}</span>
        </div>
    </div>

    <!-- Expense by Category -->
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
                    <div class="text-right flex-shrink-0 ml-3">
                        <div class="text-sm font-bold text-danger-500">
                            @if($cat['afn'] > 0) {{ number_format($cat['afn']) }} {{ __('messages.afn') }} @endif
                            @if($cat['usd'] > 0)@if($cat['afn'] > 0) · @endif {{ number_format($cat['usd']) }} {{ __('messages.usd') }} @endif
                        </div>
                        @php
                            $primary = $cat['afn'] > 0 ? $cat['afn'] : $cat['usd'];
                            $primaryTotal = $totalAFN > 0 ? $totalAFN : $totalUSD;
                        @endphp
                        <div class="w-full bg-ink-100 dark:bg-ink-800 rounded-full h-1.5 mt-1" style="width: 80px;">
                            <div class="bg-danger-400 h-1.5 rounded-full" style="width: {{ $primaryTotal > 0 ? ($primary / $primaryTotal * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                        <x-icon name="banknotes" class="w-6 h-6 text-ink-400"/>
                    </div>
                    <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_expenses') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Daily Trend Chart -->
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

    <div class="mt-4 text-center">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>
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
            datasets: [
                {
                    label: '{{ __('messages.afn') }}',
                    data: @json($dailyTrend->pluck('afn')->values()),
                    backgroundColor: 'rgba(239, 68, 68, 0.5)',
                    borderColor: '#ef4444',
                    borderWidth: 0,
                    borderRadius: 4
                },
                {
                    label: '{{ __('messages.usd') }}',
                    data: @json($dailyTrend->pluck('usd')->values()),
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: '#3b82f6',
                    borderWidth: 0,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, labels: { font: { size: 9 } } } },
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
