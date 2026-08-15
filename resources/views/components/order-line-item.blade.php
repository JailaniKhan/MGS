@props([
    'index' => 0,
    'products' => [],
    'selected' => null,
    'qty' => 1,
    'price' => 0,
    'total' => 0,
    'deletable' => true,
])

<div class="rounded-xl bg-ink-50 dark:bg-white/[0.03] mb-2 border border-ink-100 dark:border-white/[0.06] p-3 group/line">
    <div class="flex items-center gap-2">
        <select name="items[{{ $index }}][product_id]"
                class="form-select flex-1 min-w-0 text-sm"
                x-data
                @@change="$dispatch('product-changed', { index: {{ $index }}, price: $el.selectedOptions[0]?.dataset.price || 0 })">
            <option value="">{{ __('messages.select_product') }}</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" data-price="{{ $product->price }}"
                    {{ $selected == $product->id ? 'selected' : '' }}>
                    {{ $product->name }} — {{ number_format($product->price) }} {{ __('messages.afn') }}
                </option>
            @endforeach
        </select>
        @if ($deletable)
            <button type="button" class="w-11 h-11 rounded-lg bg-danger-50 dark:bg-danger-900/20 text-danger-500 flex items-center justify-center flex-shrink-0 active:scale-95 transition-transform"
                    x-on:click="removeLine({{ $index }})" aria-label="{{ __('messages.remove') }}">
                <x-icon name="x-mark" class="w-4 h-4" strokeWidth="2"/>
            </button>
        @endif
    </div>
    <div class="grid grid-cols-3 gap-2 mt-2.5">
        <div class="min-w-0">
            <span class="row-label">{{ __('messages.quantity') }}</span>
            <input type="number" name="items[{{ $index }}][qty]" step="any" min="1" dir="ltr" inputmode="numeric"
                   class="form-input text-center text-sm" value="{{ $qty }}" placeholder="1">
        </div>
        <div class="min-w-0">
            <span class="row-label">{{ __('messages.price') }}</span>
            <input type="number" name="items[{{ $index }}][price]" step="0.01" dir="ltr" inputmode="decimal"
                   class="form-input text-center text-sm font-semibold text-ink-700 dark:text-ink-200"
                   value="{{ $price }}" readonly>
        </div>
        <div class="min-w-0">
            <span class="row-label">{{ __('messages.total') }}</span>
            <div class="flex items-center justify-center h-[46px] rounded-[0.875rem] border border-transparent text-sm font-bold text-primary-600 dark:text-primary-400 tabular-nums">
                {{ number_format($total) }}
            </div>
        </div>
    </div>
</div>
