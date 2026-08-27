@extends('layouts.app')

@php
    $tileStyles = [
        'bg-brand/10 dark:bg-brand/20 text-brand',
        'bg-secondary-500/10 dark:bg-secondary-500/15 text-secondary-600 dark:text-secondary-400',
        'bg-accent-500/10 dark:bg-accent-500/15 text-accent-600 dark:text-accent-400',
    ];
@endphp

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 flex items-center justify-center flex-shrink-0">
                <x-icon name="archive-box" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.inventory') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $productCount }} {{ __('messages.products') }} &middot; {{ $categories->total() }} {{ __('messages.categories') }} &middot; {{ $units->total() }} {{ __('messages.units') }}</p>
            </div>
        </div>
        <a href="{{ route('products.create') }}" class="btn-primary btn-sm flex-shrink-0">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_product') }}
        </a>
    </div>

    {{-- Stock summary --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-brand/[0.08] dark:bg-brand/[0.12]"></div>
        <div class="relative">
            <span class="metric-label">{{ __('messages.total_stock_value') }}</span>
            <div class="mt-1.5 space-y-1">
                {{-- AFN is the headline base currency; it also acts as the fallback so the
                    card never renders empty. It disappears only for a purely-USD inventory. --}}
                @if ($hasAFN || ! $hasUSD)
                    <p class="flex items-baseline gap-1.5 text-3xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-white" dir="ltr">
                        {{ number_format((float) $totalValueAFN, 2) }}
                        <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-brand/10 text-brand leading-none">{{ __('messages.afn') }}</span>
                    </p>
                @endif
                @if ($hasUSD)
                    <p class="flex items-baseline gap-1.5 text-3xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-white" dir="ltr">
                        {{ number_format((float) $totalValueUSD, 2) }}
                        <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-secondary-500/10 text-secondary-600 dark:text-secondary-400 leading-none">{{ __('messages.usd') }}</span>
                    </p>
                @endif
            </div>
            <div class="mt-2 flex items-center justify-between">
                <p class="flex items-center gap-1.5 text-[11px] font-medium text-ink-500 dark:text-ink-400">
                    <x-icon name="cube" class="w-3.5 h-3.5 text-ink-400" strokeWidth="1.8"/>
                    {{ $productCount }} {{ __('messages.products') }}
                </p>
                @if ($lowStockCount > 0)
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-accent-500/10 text-accent-600 dark:text-accent-400 border border-accent-500/20 tabular-nums">
                        <x-icon name="exclamation-triangle" class="w-3 h-3" strokeWidth="2"/>
                        {{ $lowStockCount }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 gap-2 mb-4 page-enter" style="animation-delay: 0.08s;">
        <button type="button" onclick="switchTab('products')" class="card !p-3 text-start transition-all duration-200 active:scale-[0.98]">
            <div class="w-6 h-6 rounded-lg bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center mb-2">
                <x-icon name="exclamation-triangle" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.low_stock') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums {{ $lowStockCount > 0 ? 'text-accent-600 dark:text-accent-400' : 'text-ink-800 dark:text-ink-100' }}">{{ $lowStockCount }}</p>
        </button>
        <button type="button" onclick="switchTab('categories')" class="card !p-3 text-start transition-all duration-200 active:scale-[0.98]">
            <div class="w-6 h-6 rounded-lg bg-secondary-500/10 dark:bg-secondary-500/15 flex items-center justify-center mb-2">
                <x-icon name="tag" class="w-3.5 h-3.5 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.categories') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums text-ink-800 dark:text-ink-100">{{ $categories->total() }} / {{ $units->total() }} <span class="text-[10px] font-medium text-ink-400">{{ __('messages.units') }}</span></p>
        </button>
    </div>

    <!-- Tabs -->
    <div class="segmented mb-4 page-enter" style="animation-delay: 0.1s;">
        <button id="tab-products" class="tab-btn segmented-item segmented-item-active whitespace-nowrap" onclick="switchTab('products')">
            {{ __('messages.products') }}
            <span class="tab-count">{{ $products->total() }}</span>
        </button>
        <button id="tab-categories" class="tab-btn segmented-item whitespace-nowrap" onclick="switchTab('categories')">
            {{ __('messages.categories') }}
            <span class="tab-count">{{ $categories->total() }}</span>
        </button>
        <button id="tab-units" class="tab-btn segmented-item whitespace-nowrap" onclick="switchTab('units')">
            {{ __('messages.units') }}
            <span class="tab-count">{{ $units->total() }}</span>
        </button>
    </div>

    <!-- Products List -->
    <div id="section-products" class="tab-section page-enter" style="animation-delay: 0.13s;">
        <div class="search-bar sticky top-[3.5rem] z-10 mb-3">
            <x-icon name="magnifying-glass" class="search-icon" strokeWidth="1.8"/>
            <input type="search" inputmode="search"
                   data-list-filter="product-list"
                   data-empty-text="{{ __('messages.no_results') }}"
                   autocomplete="off"
                   placeholder="{{ __('messages.search') }}"
                   class="flex-1">
        </div>
        <div class="card overflow-hidden" id="product-list">
            <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
                @forelse ($products as $product)
                    @php
                        $tile = $tileStyles[crc32($product->name) % count($tileStyles)];
                        // The lot on the shelf per stocked pool: latest real purchase
                        // lot, falling back to the master field. With nothing on any
                        // shelf the product's own registered lot shows instead — the
                        // row never loses its lot information entirely.
                        $poolLots = collect();
                        if ((int) $product->stock_afn > 0) {
                            $poolLots['AFN'] = $latestLots->get($product->id.':AFN') ?: $product->lot_number;
                        }
                        if ((int) $product->stock_usd > 0) {
                            $poolLots['USD'] = $latestLots->get($product->id.':USD') ?: $product->lot_number;
                        }
                        if ($poolLots->isEmpty() && filled($product->lot_number)) {
                            $poolLots['ANY'] = $product->lot_number;
                        }
                        $poolLots = $poolLots->filter(fn ($lot) => filled($lot))->unique();
                    @endphp
                    <a href="{{ route('products.show', $product) }}" class="list-row block active:bg-ink-50 dark:active:bg-white/[0.03] transition-colors">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-xl {{ $tile }} flex items-center justify-center flex-shrink-0">
                                <x-icon name="cube" class="w-4 h-4" strokeWidth="1.8"/>
                            </div>
                            <div class="min-w-0 flex-1">
                                {{-- Line 1: name + price --}}
                                <div class="flex items-start justify-between gap-2">
                                    <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate min-w-0">{{ $product->name }}</div>
                                    <div class="flex-shrink-0 space-y-0.5">
                                        {{-- A currency row renders only when that price exists: a USD-only
                                            product (e.g. 10 لیټره) must not show a misleading "0 AFN". --}}
                                        @if ((float) $product->price > 0)
                                            <div class="flex items-baseline justify-end gap-1 tabular-nums" dir="ltr">
                                                <span class="text-sm font-extrabold text-ink-900 dark:text-ink-100">{{ number_format((float) $product->price, 2) }}</span>
                                                <span class="text-[9px] font-extrabold px-1 py-0.5 rounded bg-brand/10 text-brand leading-none">{{ __('messages.afn') }}</span>
                                            </div>
                                        @endif
                                        @if ($product->price_usd !== null && (float) $product->price_usd > 0)
                                            <div class="flex items-baseline justify-end gap-1 tabular-nums" dir="ltr">
                                                <span class="text-sm font-extrabold text-ink-900 dark:text-ink-100">{{ number_format((float) $product->price_usd, 2) }}</span>
                                                <span class="text-[9px] font-extrabold px-1 py-0.5 rounded bg-secondary-500/10 text-secondary-600 dark:text-secondary-400 leading-none">{{ __('messages.usd') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                {{-- Line 2: category · lot chip(s) ... stock pill(s) --}}
                                <div class="flex items-center justify-between gap-2 mt-1">
                                    <div class="flex items-center gap-1.5 min-w-0 text-[11px] text-ink-500 dark:text-ink-400">
                                        <span class="truncate">{{ $product->category?->name ?? __('messages.uncategorized') }}</span>
                                        @foreach ($poolLots as $poolCurrency => $lot)
                                            <span class="inline-flex items-center gap-0.5 flex-shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-ink-50 dark:bg-white/[0.04] text-ink-500 dark:text-ink-400 border border-ink-200 dark:border-white/[0.08]" dir="ltr">
                                                {{ __('messages.lot_short') }}&nbsp;#<span class="truncate max-w-16">{{ $lot }}</span>@if ($poolLots->count() > 1)&nbsp;({{ $poolCurrency }})@endif
                                            </span>
                                        @endforeach
                                    </div>
                                    {{-- One pill per NON-EMPTY currency pool — zero rows are noise,
                                        so an empty pool simply disappears from the list. The currency
                                        prefix renders only when two pools coexist; single-pool products
                                        take their currency from the price chip above. --}}
                                    @php($poolPills = collect([['AFN', (int) $product->stock_afn], ['USD', (int) $product->stock_usd]])->filter(fn ($pool) => $pool[1] > 0))
                                    <div class="inline-flex items-center gap-1 flex-shrink-0">
                                        @foreach ($poolPills as [$poolCurrency, $poolQty])
                                            <span class="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full tabular-nums whitespace-nowrap
                                                @if ($threshold > 0 && $poolQty < $threshold) bg-danger-50 dark:bg-danger-900/30 text-danger-600 dark:text-danger-400 border border-danger-200 dark:border-danger-700/50
                                                @else bg-primary-500/10 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400 border border-primary-500/20 dark:border-primary-500/25 @endif"
                                                dir="ltr">
                                                @if ($poolPills->count() > 1){{ $poolCurrency }} @endif{{ $poolQty }}{{ $product->unit ? ' '.($product->unit->short_name ?? $product->unit->name) : '' }}
                                            </span>
                                        @endforeach
                                        @if ($poolPills->isEmpty())
                                            <span class="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-ink-50 dark:bg-white/[0.04] text-ink-400 dark:text-ink-500 border border-ink-200 dark:border-white/[0.08]" dir="ltr">0</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <x-empty-state title="{{ __('messages.no_products') }}">
                        <x-icon name="archive-box" class="w-6 h-6 text-ink-400"/>
                    </x-empty-state>
                @endforelse
            </div>
        </div>
        @if ($products->hasPages())
            <div class="mt-3">{{ $products->links() }}</div>
        @endif
    </div>

    <!-- Categories List -->
    <div id="section-categories" class="tab-section hidden page-enter">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.categories') }}</span>
            <a href="{{ route('categories.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="w-3 h-3" strokeWidth="2"/>
                {{ __('messages.new_category') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div>
                @forelse ($categories as $category)
                    <div class="swipe-row">
                        <div class="swipe-content">
                            <div class="w-9 h-9 rounded-xl bg-secondary-500/10 dark:bg-secondary-500/15 text-secondary-600 dark:text-secondary-400 flex items-center justify-center flex-shrink-0">
                                <x-icon name="tag" class="w-4 h-4" strokeWidth="1.8"/>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $category->name }}</div>
                                <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $category->products_count }} {{ __('messages.products') }}</div>
                            </div>
                        </div>
                        <div class="swipe-actions">
                            <a href="{{ route('categories.edit', $category) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                                <x-icon name="pencil-square" class="w-4 h-4"/>
                            </a>
                            <form action="{{ route('categories.destroy', $category) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                                    <x-icon name="trash" class="w-4 h-4"/>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <x-empty-state title="{{ __('messages.no_categories') }}">
                        <x-icon name="tag" class="w-6 h-6 text-ink-400"/>
                    </x-empty-state>
                @endforelse
            </div>
        </div>
        @if ($categories->hasPages())
            <div class="mt-3">{{ $categories->links() }}</div>
        @endif
    </div>

    <!-- Units List -->
    <div id="section-units" class="tab-section hidden page-enter">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.units') }}</span>
            <a href="{{ route('units.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="w-3 h-3" strokeWidth="2"/>
                {{ __('messages.new_unit') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div>
                @forelse ($units as $unit)
                    <div class="swipe-row">
                        <div class="swipe-content">
                            <div class="w-9 h-9 rounded-xl bg-primary-500/10 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400 flex items-center justify-center flex-shrink-0">
                                <x-icon name="hashtag" class="w-4 h-4" strokeWidth="1.8"/>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">
                                    {{ $unit->name }}
                                    @if ($unit->short_name)
                                        <span class="text-xs font-medium text-ink-400">({{ $unit->short_name }})</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $unit->products_count }} {{ __('messages.products') }}</div>
                            </div>
                        </div>
                        <div class="swipe-actions">
                            <a href="{{ route('units.edit', $unit) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                                <x-icon name="pencil-square" class="w-4 h-4"/>
                            </a>
                            <form action="{{ route('units.destroy', $unit) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                                    <x-icon name="trash" class="w-4 h-4"/>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <x-empty-state title="{{ __('messages.no_units') }}">
                        <x-icon name="hashtag" class="w-6 h-6 text-ink-400"/>
                    </x-empty-state>
                @endforelse
            </div>
        </div>
        @if ($units->hasPages())
            <div class="mt-3">{{ $units->links() }}</div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-section').forEach(el => el.classList.add('hidden'));
        document.getElementById('section-' + tab).classList.remove('hidden');
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('segmented-item-active'));
        document.getElementById('tab-' + tab).classList.add('segmented-item-active');
    }

    // Keep the active tab when paging categories/units.
    @if (request()->has('categories_page'))
        switchTab('categories');
    @elseif (request()->has('units_page'))
        switchTab('units');
    @endif
</script>
@endpush
