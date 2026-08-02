@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.inventory') }}</h2>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1.5 mb-4 page-enter overflow-x-auto pb-2 scrollbar-thin" style="animation-delay: 0.05s;">
        <button id="tab-products" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-primary-500 text-white shadow-sm shadow-primary-500/20 whitespace-nowrap" onclick="switchTab('products')">
            {{ __('messages.products') }}
        </button>
        <button id="tab-categories" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-300 border border-ink-200 dark:border-ink-700 whitespace-nowrap" onclick="switchTab('categories')">
            {{ __('messages.categories') }}
        </button>
        <button id="tab-units" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-300 border border-ink-200 dark:border-ink-700 whitespace-nowrap" onclick="switchTab('units')">
            {{ __('messages.units') }}
        </button>
    </div>

    <!-- Products List -->
    <div id="section-products" class="tab-section page-enter" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.products') }}</span>
            <a href="{{ route('products.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="w-3 h-3" strokeWidth="2"/>
                {{ __('messages.new_product') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
                @forelse ($products as $product)
                    <div class="list-row">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-xl bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                                <x-icon name="archive-box" class="w-4 h-4 text-white"/>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $product->name }}</div>
                                <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $product->category->name }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                            <div class="text-right">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100">{{ number_format($product->price) }}</div>
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full
                                    @if ($product->stock < 10) bg-danger-50 dark:bg-danger-900/30 text-danger-600 dark:text-danger-400 border border-danger-200 dark:border-danger-700/50
                                    @else bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-700/50 @endif">
                                    {{ $product->stock }}{{ $product->unit ? ' ' . ($product->unit->short_name ?? $product->unit->name) : '' }}
                                </span>
                                @php
                                    $lots = $product->purchaseItems->whereNotNull('lot_number')->pluck('lot_number')->unique();
                                @endphp
                                @if ($lots->isNotEmpty())
                                    <div class="text-[10px] text-ink-500 dark:text-ink-400 mt-1">
                                        {{ __('messages.lot_number') }}: {{ $lots->implode(', ') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                            <x-icon name="archive-box" class="w-6 h-6 text-ink-400"/>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_products') }}</p>
                    </div>
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
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-secondary-500 to-secondary-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                                <x-icon name="tag" class="w-4 h-4 text-white"/>
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
                    <div class="empty-state">
                        <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                            <x-icon name="tag" class="w-6 h-6 text-ink-400"/>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_categories') }}</p>
                    </div>
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
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                                <x-icon name="bars-3" class="w-4 h-4 text-white"/>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">
                                    {{ $unit->name }}
                                    @if ($unit->short_name)
                                        <span class="text-xs text-ink-400">({{ $unit->short_name }})</span>
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
                    <div class="empty-state">
                        <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                            <x-icon name="bars-3" class="w-6 h-6 text-ink-400"/>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_units') }}</p>
                    </div>
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
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('bg-primary-500', 'text-white', 'shadow-sm', 'shadow-primary-500/20');
            el.classList.add('bg-ink-100', 'dark:bg-ink-800', 'text-ink-600', 'dark:text-ink-300', 'border', 'border-ink-200', 'dark:border-ink-700');
        });
        const activeBtn = document.getElementById('tab-' + tab);
        activeBtn.classList.remove('bg-ink-100', 'dark:bg-ink-800', 'text-ink-600', 'dark:text-ink-300', 'border', 'border-ink-200', 'dark:border-ink-700');
        activeBtn.classList.add('bg-primary-500', 'text-white', 'shadow-sm', 'shadow-primary-500/20');
    }

    // Keep the active tab when paging categories/units.
    @if (request()->has('categories_page'))
        switchTab('categories');
    @elseif (request()->has('units_page'))
        switchTab('units');
    @endif
</script>
@endpush