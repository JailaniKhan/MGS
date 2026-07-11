@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-2">{{ __('messages.stock_report') }}</h2>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.stock') }}" class="flex gap-3">
            <div class="flex-1">
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN" {{ $selectedCurrency === 'AFN' ? 'selected' : '' }}>{{ __('messages.afn') }}</option>
                    <option value="USD" {{ $selectedCurrency === 'USD' ? 'selected' : '' }}>{{ __('messages.usd') }}</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn-primary mt-5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    @if($lowStockProducts->count() > 0)
    <div class="p-4 mb-4 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-700/30 rounded-xl page-enter" style="animation-delay: 0.1s;">
        <div class="flex items-center gap-2 mb-2">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.5-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L6.303 15c-.771 1.333.192 3 1.732 3z"></path></svg>
            <h3 class="text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">{{ __('messages.low_stock') }}</h3>
        </div>
        <p class="text-[11px] text-amber-700 dark:text-amber-400 mb-2">{{ __('messages.warning') }} {{ $minStockThreshold }} {{ __('messages.needs_restock') }} {{ $lowStockProducts->count() }} {{ __('messages.selected_items') }}:</p>
        <div class="flex flex-wrap gap-2">
            @foreach($lowStockProducts as $item)
            <span class="inline-block bg-amber-100 dark:bg-amber-800/30 text-amber-800 dark:text-amber-200 px-2.5 py-1 rounded-lg text-[11px] font-medium">{{ $item['product']->name }} ({{ $item['product']->stock }})</span>
            @endforeach
        </div>
    </div>
    @endif

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 bg-secondary-50 dark:bg-secondary-900/10">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('messages.stock_summary') }}</h3>
            </div>
        </div>
        <div class="flex items-center justify-between px-4 py-3"><span class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.total_stock_value') }}</span><span class="text-sm font-bold">{{ number_format($totalStockValue, 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span></div>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('messages.detailed_stock_report') }}</h3>
            </div>
        </div>
        @foreach($stockData as $item)
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 last:border-b-0 {{ $item['is_low_stock'] ? 'bg-amber-50/50 dark:bg-amber-900/5' : '' }}">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $item['product']->name }}</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['product']->category->name ?? __('messages.uncategorized') }}</p>
                </div>
                @if($item['is_low_stock'])
                <span class="badge badge-warning">{{ __('messages.low_stock_short') }}</span>
                @endif
            </div>
            <div class="grid grid-cols-4 gap-2 text-xs">
                <div><span class="text-gray-500 dark:text-gray-400">{{ __('messages.current_stock') }}</span><p class="font-semibold text-gray-800 dark:text-gray-200">{{ $item['product']->stock }}</p></div>
                <div><span class="text-gray-500 dark:text-gray-400">{{ __('messages.value') }}</span><p class="font-semibold text-gray-800 dark:text-gray-200">{{ number_format($item['stock_value'], 2) }}</p></div>
                <div><span class="text-gray-500 dark:text-gray-400">{{ __('messages.avg_purchase') }}</span><p class="font-semibold text-gray-800 dark:text-gray-200">{{ number_format($item['avg_purchase_price'], 2) }}</p></div>
                <div><span class="text-gray-500 dark:text-gray-400">{{ __('messages.avg_sale') }}</span><p class="font-semibold text-gray-800 dark:text-gray-200">{{ number_format($item['avg_sale_price'], 2) }}</p></div>
            </div>
        </div>
        @endforeach
    </div>
@endsection