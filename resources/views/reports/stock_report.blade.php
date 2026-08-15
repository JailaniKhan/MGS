@extends('layouts.app')

@section('content')
    @php
        $currencyLabel = $selectedCurrency === 'USD' ? '$' : __('messages.afn');
        $productCount = $stockData->count();
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 border border-secondary-100 dark:border-secondary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="box" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.stock_report') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $productCount }} {{ __('messages.products') }} &middot; {{ $currencyLabel }}</p>
            </div>
        </div>
        <a href="{{ route('dashboard') }}" aria-label="{{ __('messages.back') }}"
           class="w-9 h-9 rounded-xl bg-white dark:bg-[#18191a] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 hover:border-ink-200 dark:hover:border-white/[0.12] transition-all duration-200 active:scale-95">
            <x-icon name="arrow-left" class="w-4 h-4" strokeWidth="2"/>
        </a>
    </div>

    {{-- Filter --}}
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.stock') }}" class="flex items-end gap-3">
            <div class="flex-1">
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN" {{ $selectedCurrency === 'AFN' ? 'selected' : '' }}>{{ __('messages.afn') }}</option>
                    <option value="USD" {{ $selectedCurrency === 'USD' ? 'selected' : '' }}>{{ __('messages.usd') }}</option>
                </select>
            </div>
            <button type="submit" class="btn-primary"><x-icon name="check" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.filter') }}</button>
        </form>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-3 gap-2 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.products') }}</span>
            <span class="metric-value text-ink-800 dark:text-ink-200 tabular-nums">{{ $productCount }}</span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.total_stock_value') }}</span>
            <span class="metric-value text-primary-600 dark:text-primary-400 tabular-nums">{{ number_format((float) $totalStockValue, 2) }} <span class="text-[11px] font-medium text-ink-400">{{ $currencyLabel }}</span></span>
        </div>
        <div class="stat-card">
            <span class="metric-label">{{ __('messages.low_stock') }}</span>
            <span class="metric-value text-accent-600 dark:text-accent-400 tabular-nums">{{ $lowStockProducts->count() }}</span>
        </div>
    </div>

    {{-- Low stock banner --}}
    @if($lowStockProducts->count() > 0)
        <div class="p-4 mb-4 bg-accent-50 dark:bg-accent-900/10 border border-accent-200 dark:border-accent-700/30 rounded-xl page-enter" style="animation-delay: 0.12s;">
            <div class="flex items-center gap-2 mb-2">
                <x-icon name="exclamation-triangle" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="2"/>
                <h3 class="text-xs font-bold text-accent-800 dark:text-accent-300 uppercase tracking-wider">{{ __('messages.low_stock') }}</h3>
            </div>
            <p class="text-[11px] text-accent-700 dark:text-accent-400 mb-2">{{ $lowStockProducts->count() }} {{ __('messages.selected_items') }} {{ __('messages.needs_restock') }} {{ $minStockThreshold }} {{ __('messages.items') }}:</p>
            <div class="flex flex-wrap gap-2">
                @foreach($lowStockProducts as $item)
                    <span class="inline-block bg-accent-100 dark:bg-accent-800/30 text-accent-800 dark:text-accent-200 px-2.5 py-1 rounded-lg text-[11px] font-medium tabular-nums">{{ $item['product']->name }} ({{ $item['product']->stock }})</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Detailed report --}}
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.detailed_stock_report') }}</h3>
            </div>
        </div>
        @forelse($stockData as $item)
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0 {{ $item['is_low_stock'] ? 'bg-accent-50/50 dark:bg-accent-900/5' : '' }}">
                <div class="flex items-start justify-between mb-2">
                    <div class="min-w-0">
                        <h4 class="text-sm font-medium text-ink-800 dark:text-ink-200 truncate">{{ $item['product']->name }}</h4>
                        <p class="text-xs text-ink-500 dark:text-ink-400">{{ $item['product']->category->name ?? __('messages.uncategorized') }}</p>
                    </div>
                    @if($item['is_low_stock'])
                        <span class="badge badge-warning flex-shrink-0 ms-2">{{ __('messages.low_stock_short') }}</span>
                    @endif
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs tabular-nums">
                    <div>
                        <span class="text-ink-500 dark:text-ink-400">{{ __('messages.current_stock') }}</span>
                        <p class="font-semibold text-ink-800 dark:text-ink-200">{{ $item['product']->stock }}</p>
                    </div>
                    <div>
                        <span class="text-ink-500 dark:text-ink-400">{{ __('messages.value') }}</span>
                        <p class="font-semibold text-ink-800 dark:text-ink-200">{{ number_format((float) $item['stock_value'], 2) }} <span class="text-[10px] font-medium text-ink-400">{{ $currencyLabel }}</span></p>
                    </div>
                    <div>
                        <span class="text-ink-500 dark:text-ink-400">{{ __('messages.avg_purchase') }}</span>
                        <p class="font-semibold text-ink-800 dark:text-ink-200">{{ number_format((float) $item['avg_purchase_price'], 2) }} <span class="text-[10px] font-medium text-ink-400">{{ $currencyLabel }}</span></p>
                    </div>
                    <div>
                        <span class="text-ink-500 dark:text-ink-400">{{ __('messages.avg_sale') }}</span>
                        <p class="font-semibold text-ink-800 dark:text-ink-200">{{ number_format((float) $item['avg_sale_price'], 2) }} <span class="text-[10px] font-medium text-ink-400">{{ $currencyLabel }}</span></p>
                    </div>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="empty-illustration">
                    <x-icon name="box" class="w-6 h-6 text-ink-400"/>
                </div>
                <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_products') }}</p>
            </div>
        @endforelse
    </div>
@endsection