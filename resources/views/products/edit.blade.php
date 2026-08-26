@extends('layouts.app')

@php
    // A lot is priced in ONE currency: the picker follows whichever side
    // currently holds a price (AFN wins when both legacy values exist).
    $priceCurrency = old('price_currency', ((float) $product->price > 0 || (float) ($product->price_usd ?? 0) <= 0) ? 'AFN' : 'USD');
    $priceValue = old('price', $priceCurrency === 'USD' ? $product->price_usd : $product->price);
    // Once a product holds a price its currency is locked — switching it
    // would strand stock in the old pool. Only unpriced products pick a side.
    $currencyLocked = (float) $product->price > 0 || (float) ($product->price_usd ?? 0) > 0;
    // Stock follows the same side: one visible field edits the priced pool,
    // the other pool travels as a hidden input.
    $stockValue = $priceCurrency === 'USD' ? old('stock_usd', $product->stock_usd) : old('stock_afn', $product->stock_afn);
@endphp

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 flex items-center justify-center flex-shrink-0">
                <x-icon name="cube" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.edit_product') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.inventory') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('inventory.index') }}"/>
    </div>

    <form action="{{ route('products.update', $product) }}" method="POST">
        @csrf @method('PUT')

        {{-- Section: details --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="tag" class="w-4 h-4 text-brand"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.details') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.name') }}</label>
                    <div class="relative">
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" required class="form-input ps-10">
                        <x-icon name="cube" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    </div>
                    @error('name') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">{{ __('messages.lot_number') }}</label>
                    <div class="relative">
                        <input type="text" name="lot_number" value="{{ old('lot_number', $product->lot_number) }}" class="form-input ps-10" placeholder="{{ __('messages.lot_auto_generate') }}">
                        <x-icon name="hashtag" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    </div>
                    <p class="text-[10px] text-ink-400 mt-1">{{ __('messages.lot_help_edit') }}</p>
                    @error('lot_number') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">{{ __('messages.category') }}</label>
                    <x-searchable-select name="category_id" required placeholder="{{ __('messages.select_option') }}" :selected="old('category_id', $product->category_id)" :options="$categoryOptions" />
                    @error('category_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('messages.unit') }}</label>
                    <x-searchable-select name="unit_id" placeholder="{{ __('messages.select_option') }}" :selected="old('unit_id', $product->unit_id)" :options="$unitOptions" />
                    @error('unit_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Section: price & stock --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="banknotes" class="w-4 h-4 text-brand"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.price') }} / {{ __('messages.stock') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="form-label mb-0">{{ __('messages.price') }}</label>
                        <span class="text-[10px] font-bold uppercase tracking-wide text-ink-400">{{ __('messages.optional') }}</span>
                    </div>
                    {{-- A priced product's currency is locked; only unpriced
                        products still pick a side. --}}
                    @if ($currencyLocked)
                        <div class="mb-2 px-4 py-3.5 rounded-xl border border-brand/30 bg-brand/10 dark:bg-brand/20 text-sm font-bold text-brand text-center">
                            {{ $priceCurrency === 'USD' ? '$ · '.__('messages.usd') : __('messages.afn') }}
                        </div>
                        <input type="hidden" name="price_currency" value="{{ $priceCurrency }}">
                    @else
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <x-radio-pill name="price_currency" value="AFN" :label="__('messages.afn')" :checked="$priceCurrency === 'AFN'" pill/>
                            <x-radio-pill name="price_currency" value="USD" :label="'$'" :checked="$priceCurrency === 'USD'" pill/>
                        </div>
                    @endif
                    <div class="relative">
                        <input type="number" name="price" value="{{ $priceValue }}" step="0.01" min="0" dir="ltr" inputmode="decimal" placeholder="0" class="form-input pe-12 text-center font-bold tabular-nums">
                        <span data-price-badge="AFN" class="pointer-events-none absolute top-1/2 -translate-y-1/2 end-3 text-[9px] font-extrabold px-1 py-0.5 rounded bg-brand/10 text-brand {{ $priceCurrency === 'USD' ? 'hidden' : '' }}">{{ __('messages.afn') }}</span>
                        <span data-price-badge="USD" class="pointer-events-none absolute top-1/2 -translate-y-1/2 end-3 text-[9px] font-extrabold px-1 py-0.5 rounded bg-secondary-500/10 text-secondary-600 dark:text-secondary-400 {{ $priceCurrency === 'USD' ? '' : 'hidden' }}">{{ __('messages.usd') }}</span>
                    </div>
                    @error('price') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                    @error('price_currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                {{-- Stock lives in the pool of the priced currency: one visible
                    field follows the picker above; the other pool travels as a
                    hidden input so switching sides loses nothing. --}}
                <div>
                    <label class="form-label">{{ __('messages.stock') }}</label>
                    <input type="hidden" name="stock_afn" value="{{ old('stock_afn', $product->stock_afn) }}" data-stock-pool="AFN">
                    <input type="hidden" name="stock_usd" value="{{ old('stock_usd', $product->stock_usd) }}" data-stock-pool="USD">
                    <div class="relative">
                        <input type="number" data-stock-active data-currency="{{ $priceCurrency }}" value="{{ $stockValue }}" min="0" required dir="ltr" inputmode="numeric" class="form-input pe-12 text-center font-bold tabular-nums">
                        <span data-stock-badge="AFN" class="pointer-events-none absolute top-1/2 -translate-y-1/2 end-3 text-[9px] font-extrabold px-1 py-0.5 rounded bg-brand/10 text-brand {{ $priceCurrency === 'USD' ? 'hidden' : '' }}">{{ __('messages.afn') }}</span>
                        <span data-stock-badge="USD" class="pointer-events-none absolute top-1/2 -translate-y-1/2 end-3 text-[9px] font-extrabold px-1 py-0.5 rounded bg-secondary-500/10 text-secondary-600 dark:text-secondary-400 {{ $priceCurrency === 'USD' ? '' : 'hidden' }}">{{ __('messages.usd') }}</span>
                    </div>
                    @error('stock_afn') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                    @error('stock_usd') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mt-3">
                    <label class="form-label">{{ __('messages.description') }}</label>
                    <textarea name="description" rows="2" class="form-input resize-none" placeholder="{{ __('messages.notes') }}">{{ old('description', $product->description) }}</textarea>
                    @error('description') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full page-enter" style="animation-delay: 0.3s;">
            <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
            {{ __('messages.save') }}
        </button>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const pools = {};
        document.querySelectorAll('[data-stock-pool]').forEach((input) => {
            pools[input.dataset.stockPool] = input;
        });
        const activeStock = document.querySelector('[data-stock-active]');
        let currency = activeStock?.dataset.currency
            || document.querySelector('input[name="price_currency"]:checked')?.value
            || 'AFN';

        if (activeStock) {
            activeStock.addEventListener('input', () => {
                pools[currency].value = activeStock.value;
            });
        }

        document.querySelectorAll('input[name="price_currency"]').forEach((radio) => {
            radio.addEventListener('change', (e) => {
                document.querySelectorAll('[data-price-badge]').forEach((badge) => {
                    badge.classList.toggle('hidden', badge.dataset.priceBadge !== e.target.value);
                });
                document.querySelectorAll('[data-stock-badge]').forEach((badge) => {
                    badge.classList.toggle('hidden', badge.dataset.stockBadge !== e.target.value);
                });

                const next = e.target.value;
                if (next === currency || !activeStock) return;
                // Stock moves with the currency: the total is carried into the
                // new pool and the old one is zeroed (the server records the
                // move as pool adjustment movements).
                const carried = parseInt(pools[currency].value, 10) || 0;
                pools[next].value = (parseInt(pools[next].value, 10) || 0) + carried;
                pools[currency].value = 0;
                currency = next;
                activeStock.value = pools[next].value;
            });
        });
    })();
</script>
@endpush
