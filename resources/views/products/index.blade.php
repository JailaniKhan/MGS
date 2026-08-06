@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.products') }}</h2>
        <a href="{{ route('products.create') }}" class="btn-primary btn-sm">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_product') }}
        </a>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($products as $product)
                <div class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded-xl bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                            <x-icon name="archive-box" class="w-4 h-4 text-white"/>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $product->name }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">
                                {{ $product->category->name }}
                                @if($product->lot_number)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-ink-100 dark:bg-ink-800 text-[10px] font-semibold text-ink-500 dark:text-ink-400 ms-1">{{ __('messages.lot_number') }}: {{ $product->lot_number }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                        <div class="text-right">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100">{{ number_format($product->price) }}</div>
                            <div class="mt-0.5">
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full
                                    @if ($product->stock < 10) bg-danger-50 dark:bg-danger-900/30 text-danger-600 dark:text-danger-400 border border-danger-200 dark:border-danger-700/50
                                    @else bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-700/50 @endif">
                                    {{ $product->stock }}{{ $product->unit ? ' ' . ($product->unit->short_name ?? $product->unit->name) : '' }}
                                </span>
                            </div>
                        </div>
                        <a href="{{ route('products.edit', $product) }}" class="p-2 text-ink-400 hover:text-secondary-500 transition-colors">
                            <x-icon name="pencil" class="w-4 h-4"/>
                        </a>
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
        <div class="mt-4">{{ $products->links() }}</div>
    @endif
@endsection