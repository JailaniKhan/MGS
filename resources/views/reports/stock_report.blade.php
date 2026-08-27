@extends('layouts.app')

@section('content')
    @php
        $currencyLabel = $selectedCurrency === 'USD' ? '$' : __('messages.afn');
        $productCount = $stockData->count();
        $lowCount = $lowStockProducts->count();

        // Biggest holdings first — the report's job is answering "where is my money parked".
        $sortedStock = $stockData->sortByDesc('stock_value')->values();

        $shareOf = function ($amount, $total) {
            if (bccomp((string) $total, '0', 2) !== 1) return 0;
            return (int) min(100, round(bcmul(bcdiv((string) $amount, (string) $total, 4), '100', 0)));
        };
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 border border-secondary-100 dark:border-secondary-800/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="archive-box" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.stock_report') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $productCount }} {{ __('messages.products') }} &middot; {{ $currencyLabel }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('dashboard') }}"/>
    </div>

    {{-- Filter --}}
    <div class="card p-3.5 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.stock') }}" class="grid grid-cols-2 gap-3">
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

    {{-- Hero: total stock value --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.1s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-36 h-36 rounded-full bg-secondary-500/[0.08] dark:bg-secondary-400/[0.08]"></div>
        <div class="relative">
            <div class="flex items-center justify-between gap-3 mb-2">
                <span class="metric-label">{{ __('messages.total_stock_value') }}</span>
                <span class="badge {{ $lowCount > 0 ? 'badge-warning' : 'badge-success' }}">
                    <x-icon name="{{ $lowCount > 0 ? 'exclamation-triangle' : 'shield-check' }}" class="w-3.5 h-3.5" strokeWidth="2"/>
                    @if($lowCount > 0)
                        {{ $lowCount }} {{ __('messages.low_stock_short') }}
                    @else
                        {{ $productCount }} {{ __('messages.products') }}
                    @endif
                </span>
            </div>
            <p class="text-3xl font-extrabold tabular-nums tracking-tight text-secondary-600 dark:text-secondary-400" dir="ltr">
                {{ number_format((float) $totalStockValue, 2) }} <span class="text-sm font-bold text-ink-400 dark:text-ink-500">{{ $currencyLabel }}</span>
            </p>
            <p class="mt-2.5 flex items-center gap-1.5 text-[11px] font-medium text-ink-500 dark:text-ink-400">
                <x-icon name="cube" class="w-3.5 h-3.5 text-ink-400"/>
                {{ $productCount }} {{ __('messages.products') }}@if($lowCount > 0) &middot; {{ $lowCount }} {{ __('messages.needs_restock') }} (&lt; {{ $minStockThreshold }} {{ __('messages.units') }})@endif
            </p>
        </div>
    </div>

    {{-- Supporting tiles --}}
    <div class="grid grid-cols-2 gap-2 mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="card !p-3 relative overflow-hidden">
            <div class="w-6 h-6 rounded-lg bg-secondary-50 dark:bg-secondary-900/30 flex items-center justify-center mb-2">
                <x-icon name="cube" class="w-3.5 h-3.5 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.products') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums text-ink-800 dark:text-ink-100">{{ $productCount }}</p>
        </div>
        <div class="card !p-3 relative overflow-hidden">
            <div class="w-6 h-6 rounded-lg bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center mb-2">
                <x-icon name="exclamation-triangle" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.low_stock') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums {{ $lowCount > 0 ? 'text-accent-600 dark:text-accent-400' : 'text-ink-800 dark:text-ink-100' }}">{{ $lowCount }}</p>
        </div>
    </div>

    {{-- Low stock banner --}}
    @if($lowCount > 0)
        <div class="p-4 mb-4 bg-accent-50 dark:bg-accent-900/10 border border-accent-200 dark:border-accent-700/30 rounded-2xl page-enter" style="animation-delay: 0.18s;">
            <div class="flex items-center gap-2 mb-1.5">
                <div class="w-8 h-8 rounded-xl bg-accent-100 dark:bg-accent-900/30 flex items-center justify-center flex-shrink-0">
                    <x-icon name="exclamation-triangle" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="2"/>
                </div>
                <div class="min-w-0">
                    <h3 class="text-xs font-bold text-accent-800 dark:text-accent-300">{{ __('messages.low_stock') }}</h3>
                    <p class="text-[11px] text-accent-700 dark:text-accent-400">{{ $lowCount }} {{ __('messages.products') }} {{ __('messages.needs_restock') }} (&lt; {{ $minStockThreshold }} {{ __('messages.units') }})</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-1.5 mt-1">
                @foreach($lowStockProducts as $item)
                    <span class="inline-flex items-center gap-1 bg-accent-100 dark:bg-accent-800/30 text-accent-800 dark:text-accent-200 px-2.5 py-1 rounded-lg text-[11px] font-semibold tabular-nums">
                        {{ $item['product']->name }}
                        <span class="opacity-70">({{ $item['pool_qty'] }})</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Search --}}
    <x-search-filter-bar id="stock-search" data-list-filter="stock-list" :empty-text="__('messages.no_results')" />

    {{-- Detailed report --}}
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.25s;" id="stock-list">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-secondary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.detailed_stock_report') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/25">
            @forelse($sortedStock as $item)
                @php
                    $product = $item['product'];
                    $share = $shareOf($item['stock_value'], $totalStockValue);
                    // A unit only earns a margin hint when both prices exist and selling beats buying.
                    $hasMargin = bccomp($item['avg_purchase_price'], '0', 2) === 1
                        && bccomp($item['avg_sale_price'], $item['avg_purchase_price'], 2) === 1;
                @endphp
                <div class="px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-xl {{ $item['is_low_stock'] ? 'bg-accent-50 dark:bg-accent-900/25 text-accent-600 dark:text-accent-400' : 'bg-secondary-50 dark:bg-secondary-900/25 text-secondary-600 dark:text-secondary-400' }} flex items-center justify-center flex-shrink-0">
                                <x-icon name="cube" class="w-4 h-4" strokeWidth="1.8"/>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $product->name }}</h4>
                                    <span class="inline-flex items-center flex-shrink-0 px-2 py-0.5 rounded-md bg-secondary-50 dark:bg-secondary-900/25 text-[11px] font-bold text-secondary-700 dark:text-secondary-300 tabular-nums">
                                        <x-icon name="cube" class="w-3 h-3 me-1" strokeWidth="2"/>
                                        <bdi>{{ $item['pool_qty'] }} {{ $product->unit ? ($product->unit->short_name ?? $product->unit->name) : __('messages.units') }}</bdi>
                                    </span>
                                    @if($item['is_low_stock'])
                                        <span class="badge badge-warning flex-shrink-0">{{ __('messages.low_stock_short') }}</span>
                                    @endif
                                </div>
                                @php
                                    $catLine = e($product->category->name ?? __('messages.uncategorized'));
                                    if (!empty($product->lot_number)) {
                                        $catLine .= ' &middot; <span class="font-semibold text-ink-500 dark:text-ink-400">'.e(__('messages.lot_number')).': '.e($product->lot_number).'</span>';
                                    }
                                    $statParts = [];
                                    if ((float) $item['avg_purchase_price'] > 0) {
                                        $statParts[] = __('messages.avg_purchase').' <bdi>'.number_format((float) $item['avg_purchase_price'], 2).'</bdi>';
                                    }
                                    if ((float) $item['avg_sale_price'] > 0) {
                                        $statParts[] = __('messages.avg_sale').' <bdi>'.number_format((float) $item['avg_sale_price'], 2).'</bdi>';
                                    }
                                    if ($share > 0) {
                                        $statParts[] = '<span class="font-semibold">'.$share.'%</span>';
                                    }
                                @endphp
                                <p class="text-[11px] text-ink-400 dark:text-ink-500 mt-0.5 truncate">{!! $catLine !!}</p>
                                @if($statParts)
                                    <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-1 tabular-nums truncate">{!! implode(' &middot; ', $statParts) !!}</p>
                                @endif
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0 ms-3">
                            <div class="text-sm font-extrabold tabular-nums text-ink-900 dark:text-ink-100" dir="ltr">{{ number_format((float) $item['stock_value'], 2) }}</div>
                            <div class="text-[10px] font-medium text-ink-400 dark:text-ink-500">{{ $currencyLabel }}</div>
                            @if($hasMargin)
                                <div class="mt-0.5 inline-flex items-center gap-0.5 text-[10px] font-bold text-primary-600 dark:text-primary-400 tabular-nums" dir="ltr" title="{{ __('messages.avg_sale') }} - {{ __('messages.avg_purchase') }}">
                                    <x-icon name="arrow-trending-up" class="w-3 h-3" strokeWidth="2"/>+{{ number_format((float) bcsub($item['avg_sale_price'], $item['avg_purchase_price'], 2), 2) }}
                                </div>
                            @endif
                        </div>
                    </div>
                    @if($share > 0)
                        <div class="mt-2 h-1 rounded-full bg-ink-100 dark:bg-white/[0.06] overflow-hidden">
                            <div class="h-full rounded-full bg-secondary-400 dark:bg-secondary-500/70" style="width: {{ $share }}%;"></div>
                        </div>
                    @endif
                </div>
            @empty
                <x-empty-state title="{{ __('messages.no_products') }}">
                    <x-icon name="archive-box" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
        <div class="flex items-center justify-between px-4 py-3.5 bg-secondary-50 dark:bg-secondary-900/10 tabular-nums">
            <span class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.total_stock_value') }}</span>
            <span class="text-sm font-extrabold text-secondary-700 dark:text-secondary-300" dir="ltr">{{ number_format((float) $totalStockValue, 2) }} {{ $currencyLabel }}</span>
        </div>
    </div>
@endsection
