@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.inventory') }}</h2>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1.5 mb-4 page-enter" style="animation-delay: 0.05s;">
        <button id="tab-products" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-primary-500 text-white shadow-sm shadow-primary-500/20" onclick="switchTab('products')">
            {{ __('messages.products') }}
        </button>
        <button id="tab-categories" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-300 border border-ink-200 dark:border-ink-700" onclick="switchTab('categories')">
            {{ __('messages.categories') }}
        </button>
        <button id="tab-units" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-300 border border-ink-200 dark:border-ink-700" onclick="switchTab('units')">
            {{ __('messages.units') }}
        </button>
    </div>

    <!-- Products List -->
    <div id="section-products" class="tab-section page-enter" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.products') }}</span>
            <a href="{{ route('products.create') }}" class="btn-primary btn-sm">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                {{ __('messages.new_product') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
                @forelse ($products as $product)
                    <div class="list-row">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-xl bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                                </svg>
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
                            <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_products') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Categories List -->
    <div id="section-categories" class="tab-section hidden page-enter">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.categories') }}</span>
            <a href="{{ route('categories.create') }}" class="btn-primary btn-sm">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                {{ __('messages.new_category') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div>
                @forelse ($categories as $category)
                    <div class="swipe-row">
                        <div class="swipe-content">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-secondary-500 to-secondary-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $category->name }}</div>
                                <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $category->products_count }} {{ __('messages.products') }}</div>
                            </div>
                        </div>
                        <div class="swipe-actions">
                            <a href="{{ route('categories.edit', $category) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                </svg>
                            </a>
                            <form action="{{ route('categories.destroy', $category) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_categories') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Units List -->
    <div id="section-units" class="tab-section hidden page-enter">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.units') }}</span>
            <a href="{{ route('units.create') }}" class="btn-primary btn-sm">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                {{ __('messages.new_unit') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            <div>
                @forelse ($units as $unit)
                    <div class="swipe-row">
                        <div class="swipe-content">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                                </svg>
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
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                </svg>
                            </a>
                            <form action="{{ route('units.destroy', $unit) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                @csrf @method('DELETE')
                                <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_units') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
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
</script>
@endpush