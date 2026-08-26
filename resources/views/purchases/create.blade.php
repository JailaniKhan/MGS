@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="shopping-bag" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.new_purchase') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.supplier') }} &middot; {{ __('messages.products') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('orders.index') }}"/>
    </div>

    <form action="{{ route('purchases.store') }}" method="POST">
        @csrf

        {{-- Section: party & currency --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="user" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.select_party') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.select_party') }}</label>
                    <x-searchable-select name="person" required placeholder="{{ __('messages.select_party') }}" :selected="old('person')" :options="$personOptions" />
                    @error('person') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('messages.currency_unit') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                            <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="sr-only">
                            <span>{{ __('messages.afn') }}</span>
                        </label>
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                            <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="sr-only">
                            <span>$</span>
                        </label>
                    </div>
                    @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Section: products --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="cube" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.products') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div id="products-container" class="space-y-2.5">
                    <div class="product-row rounded-xl border border-ink-100 dark:border-white/[0.06] bg-ink-50 dark:bg-white/[0.03] p-3">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <x-searchable-select name="products[0][product_id]" required select-class="product-select" placeholder="{{ __('messages.product') }}" :options="$productOptions" />
                            </div>
                            <button type="button" class="remove-product w-11 h-11 rounded-lg text-danger-400 hover:text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-900/20 flex items-center justify-center transition-colors flex-shrink-0" aria-label="{{ __('messages.remove') }}">
                                <x-icon name="x-mark" class="w-4 h-4" strokeWidth="2"/>
                            </button>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-2.5">
                            <div class="min-w-0">
                                <span class="row-label">{{ __('messages.quantity') }}</span>
                                <input type="number" name="products[0][quantity]" min="1" value="1" dir="ltr" inputmode="numeric" required class="product-qty w-full form-input text-center">
                            </div>
                            <div class="min-w-0">
                                <span class="row-label">{{ __('messages.price') }}</span>
                                <input type="number" name="products[0][unit_price]" min="0" step="0.01" value="" dir="ltr" inputmode="decimal" placeholder="0.00" required class="product-price w-full form-input text-center">
                            </div>
                            <div class="min-w-0">
                                <span class="row-label">{{ __('messages.lot_number') }}</span>
                                <input type="text" name="products[0][lot_number]" placeholder="—" class="product-lot w-full form-input text-center">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" id="add-product" class="inline-flex items-center gap-1.5 text-accent-600 dark:text-accent-400 text-sm font-bold mt-3 active:scale-95 transition-transform"><x-icon name="plus" class="w-4 h-4" strokeWidth="2.2"/>{{ __('messages.another_product') }}</button>
            </div>
        </div>

        {{-- Totals --}}
        <div class="bg-accent-500/5 dark:bg-accent-500/10 border border-accent-500/15 dark:border-accent-500/20 rounded-2xl p-4 mb-4 space-y-2 page-enter" style="animation-delay: 0.25s;">
            <div class="flex justify-between text-sm">
                <span class="text-ink-600 dark:text-ink-400">{{ __('messages.subtotal') }}:</span>
                <span id="subtotal-amount" class="font-semibold tabular-nums text-ink-900 dark:text-ink-100">0 {{ __('messages.afn') }}</span>
            </div>
            <div class="flex justify-between text-sm border-t border-accent-500/15 dark:border-accent-500/20 pt-2 mt-1">
                <span class="font-bold text-ink-900 dark:text-ink-100">{{ __('messages.total_amount') }}:</span>
                <span id="total-amount" class="font-extrabold tabular-nums text-accent-600 dark:text-accent-400">0 {{ __('messages.afn') }}</span>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full page-enter" style="animation-delay: 0.3s;">
            <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
            {{ __('messages.complete_purchase') }}
        </button>
    </form>
@endsection

@push('scripts')
@vite('resources/js/line-items.js')
<script>
    window.addEventListener('DOMContentLoaded', () => MGSLineItems.init({
        afnLabel: @json(__('messages.afn')),
        filterByCurrency: true,
    }));
</script>
@endpush
