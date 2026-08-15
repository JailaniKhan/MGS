@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>
            {{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_purchase') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('purchases.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.select_party') }}</label>
                <x-searchable-select name="person" required placeholder="{{ __('messages.select_party') }}" :selected="old('person')" :options="$personOptions" />
                @error('person') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center justify-center gap-2 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-colors">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm font-medium">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-colors">
                        <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm font-medium">USD ($)</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.products') }}</label>
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
                <button type="button" id="add-product" class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-400 text-sm font-bold mt-3 active:scale-95 transition-transform"><x-icon name="plus" class="w-4 h-4" strokeWidth="2.2"/>{{ __('messages.another_product') }}</button>
            </div>

            <div class="bg-primary-50 dark:bg-primary-900/10 rounded-xl p-3.5 mb-4 space-y-1.5">
                <div class="flex justify-between text-sm">
                    <span class="text-ink-600 dark:text-ink-400">{{ __('messages.subtotal') }}:</span>
                    <span id="subtotal-amount" class="font-semibold text-ink-900 dark:text-ink-100">0 {{ __('messages.afn') }}</span>
                </div>
                <div class="flex justify-between text-sm border-t border-primary-200 dark:border-primary-800/30 pt-1.5 mt-1.5">
                    <span class="font-bold text-ink-900 dark:text-ink-100">{{ __('messages.total_amount') }}:</span>
                    <span id="total-amount" class="font-extrabold text-primary-700 dark:text-primary-400">0 {{ __('messages.afn') }}</span>
                </div>
            </div>

            <button type="submit" class="w-full btn-primary">{{ __('messages.complete_purchase') }}</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    let productIndex = 1;

    function getCurrencySymbol() {
        return document.querySelector('input[name="currency"]:checked')?.value === 'USD' ? '$' : '{{ __('messages.afn') }}';
    }

    function updateTotal() {
        let subtotal = 0;
        const symbol = getCurrencySymbol();
        document.querySelectorAll('.product-row').forEach(row => {
            const qty = row.querySelector('.product-qty');
            const price = row.querySelector('.product-price');
            if (qty.value && price.value) {
                subtotal += parseFloat(price.value) * parseInt(qty.value);
            }
        });

        document.getElementById('subtotal-amount').textContent = subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + symbol;
        document.getElementById('total-amount').textContent = subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + symbol;
    }

    function applyProductDefaults(row) {
        const select = row.querySelector('.product-select');
        const lotInput = row.querySelector('.product-lot');
        if (select.value && select.selectedIndex >= 0) {
            const lot = select.options[select.selectedIndex].dataset.lot;
            if (lot && ! lotInput.value) {
                lotInput.value = lot;
            }
        }
    }

    document.querySelectorAll('input[name="currency"]').forEach(radio => {
        radio.addEventListener('change', updateTotal);
    });

    document.getElementById('add-product').addEventListener('click', function() {
        const container = document.getElementById('products-container');
        const firstRow = container.querySelector('.product-row');
        const newRow = firstRow.cloneNode(true);

        newRow.querySelector('.product-select').name = `products[${productIndex}][product_id]`;
        newRow.querySelector('.product-select').value = '';
        newRow.querySelector('.product-qty').name = `products[${productIndex}][quantity]`;
        newRow.querySelector('.product-qty').value = 1;
        newRow.querySelector('.product-price').name = `products[${productIndex}][unit_price]`;
        newRow.querySelector('.product-price').value = '';
        newRow.querySelector('.product-lot').name = `products[${productIndex}][lot_number]`;
        newRow.querySelector('.product-lot').value = '';

        newRow.classList.add('flex-wrap');

        const searchable = newRow.querySelector('[data-searchable]');
        if (searchable) {
            delete searchable.dataset.searchableInitialized;
            initSearchableSelect(searchable);
        }

        newRow.querySelector('.product-qty').addEventListener('input', updateTotal);
        newRow.querySelector('.product-price').addEventListener('input', updateTotal);
        newRow.querySelector('.product-select').addEventListener('change', function() {
            applyProductDefaults(this.closest('.product-row'));
            updateTotal();
        });
        newRow.querySelector('.remove-product').addEventListener('click', function() {
            if (container.querySelectorAll('.product-row').length > 1) {
                newRow.remove();
                updateTotal();
            }
        });

        container.appendChild(newRow);
        productIndex++;
    });

    document.querySelectorAll('.product-qty').forEach(input => {
        input.addEventListener('input', updateTotal);
    });
    document.querySelectorAll('.product-price').forEach(input => {
        input.addEventListener('input', updateTotal);
    });
    document.querySelectorAll('.product-select').forEach(select => {
        select.addEventListener('change', function() {
            applyProductDefaults(this.closest('.product-row'));
            updateTotal();
        });
    });
    document.querySelectorAll('.remove-product').forEach(btn => {
        btn.addEventListener('click', function() {
            const container = document.getElementById('products-container');
            if (container.querySelectorAll('.product-row').length > 1) {
                this.closest('.product-row').remove();
                updateTotal();
            }
        });
    });

    updateTotal();
</script>
@endpush
