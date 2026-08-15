@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>
            {{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_order') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('orders.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.customer') }}</label>
                <x-searchable-select name="person" required placeholder="{{ __('messages.select_customer') }}" :selected="old('person')" :options="$personOptions" />
                @error('person') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/30">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm font-medium">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/30">
                        <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm font-medium">USD ($)</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.products') }}</label>
                <div id="products-container" class="space-y-2">
                    <div class="product-row flex items-center gap-2 flex-wrap">
                        <div class="flex-1 min-w-[180px]">
                            <x-searchable-select name="products[0][product_id]" required select-class="product-select" placeholder="{{ __('messages.product') }}" :options="$productOptions" />
                        </div>
                        <input type="number" name="products[0][unit_price]" min="0.01" step="0.01" value="" placeholder="{{ __('messages.price') }}" required class="product-price w-24 form-input text-center">
                        <input type="number" name="products[0][quantity]" min="1" value="1" required class="product-qty w-20 form-input text-center">
                        <input type="text" name="products[0][lot_number]" placeholder="{{ __('messages.lot_number') }}" class="product-lot w-24 form-input text-center" list="lot-suggestions">
                        <button type="button" class="remove-product w-10 h-10 rounded-lg text-danger-400 hover:text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-900/20 flex items-center justify-center transition-colors" aria-label="{{ __('messages.remove') }}">
                            <x-icon name="x-mark" class="w-4 h-4" strokeWidth="2"/>
                        </button>
                    </div>
                </div>
                <datalist id="lot-suggestions"></datalist>
                <p class="text-[10px] text-ink-400 mt-1" id="lot-hint"></p>
                <button type="button" id="add-product" class="text-primary-600 dark:text-primary-400 text-sm font-bold mt-2">+ {{ __('messages.another_product') }}</button>
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

            <button type="submit" class="w-full btn-primary">{{ __('messages.create_order') }}</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    let productIndex = 1;
    const productLots = @json($productLots);

    function getCurrencySymbol() {
        return document.querySelector('input[name="currency"]:checked')?.value === 'USD' ? '$' : '{{ __('messages.afn') }}';
    }

    function applyProductDefaults(row) {
        const select = row.querySelector('.product-select');
        const priceInput = row.querySelector('.product-price');
        const lotInput = row.querySelector('.product-lot');
        const hint = document.getElementById('lot-hint');

        if (select.value && select.selectedIndex >= 0) {
            const price = parseFloat(select.options[select.selectedIndex].dataset.price);
            if (! priceInput.value) {
                priceInput.value = isNaN(price) ? '' : price;
            }

            const productLot = select.options[select.selectedIndex].dataset.lot;
            if (productLot && ! lotInput.value) {
                lotInput.value = productLot;
            }

            const lots = productLots[select.value] || [];
            if (productLot && ! lots.includes(productLot)) {
                lots.unshift(productLot);
            }
            const datalist = document.getElementById('lot-suggestions');
            datalist.innerHTML = lots.map(l => `<option value="${l}">`).join('');
            if (lots.length) {
                hint.textContent = '{{ __('messages.available_lots') }}: ' + lots.join(', ');
            } else {
                hint.textContent = '';
            }
        } else {
            lotInput.value = '';
            hint.textContent = '';
        }
    }

    function updateTotal() {
        let subtotal = 0;
        const symbol = getCurrencySymbol();
        document.querySelectorAll('.product-row').forEach(row => {
            const select = row.querySelector('.product-select');
            const qty = row.querySelector('.product-qty');
            const priceInput = row.querySelector('.product-price');
            if (select.value && qty.value) {
                const price = parseFloat(priceInput.value) || parseFloat(select.options[select.selectedIndex]?.dataset.price) || 0;
                subtotal += price * parseInt(qty.value);
            }
        });

        document.getElementById('subtotal-amount').textContent = subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + symbol;
        document.getElementById('total-amount').textContent = subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + symbol;
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
        newRow.querySelector('.product-price').name = `products[${productIndex}][unit_price]`;
        newRow.querySelector('.product-price').value = '';
        newRow.querySelector('.product-qty').name = `products[${productIndex}][quantity]`;
        newRow.querySelector('.product-qty').value = 1;
        newRow.querySelector('.product-lot').name = `products[${productIndex}][lot_number]`;
        newRow.querySelector('.product-lot').value = '';

        newRow.classList.add('flex-wrap');

        const searchable = newRow.querySelector('[data-searchable]');
        if (searchable) {
            delete searchable.dataset.searchableInitialized;
            initSearchableSelect(searchable);
        }

        newRow.querySelector('.product-select').addEventListener('change', function() {
            applyProductDefaults(this.closest('.product-row'));
            updateTotal();
        });
        newRow.querySelector('.product-price').addEventListener('input', updateTotal);
        newRow.querySelector('.product-qty').addEventListener('input', updateTotal);
        newRow.querySelector('.remove-product').addEventListener('click', function() {
            if (container.querySelectorAll('.product-row').length > 1) {
                newRow.remove();
                updateTotal();
            }
        });

        container.appendChild(newRow);
        productIndex++;
    });

    document.querySelectorAll('.product-select').forEach(select => {
        select.addEventListener('change', function() {
            applyProductDefaults(this.closest('.product-row'));
            updateTotal();
        });
    });
    document.querySelectorAll('.product-price').forEach(input => {
        input.addEventListener('input', updateTotal);
    });
    document.querySelectorAll('.product-qty').forEach(input => {
        input.addEventListener('input', updateTotal);
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
