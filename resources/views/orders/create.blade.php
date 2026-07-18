@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            {{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_order') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('orders.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.customer') }}</label>
                <select name="person" required class="form-select">
                    <option value="">-- {{ __('messages.select_customer') }}</option>
                    <optgroup label="{{ __('messages.customers') }}">
                        @foreach ($customers as $customer)
                            <option value="customer:{{ $customer->id }}" {{ old('person') == 'customer:'.$customer->id ? 'selected' : '' }}>{{ $customer->name }} ({{ $customer->phone }})</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="{{ __('messages.suppliers') }}">
                        @foreach ($suppliers as $supplier)
                            <option value="supplier:{{ $supplier->id }}" {{ old('person') == 'supplier:'.$supplier->id ? 'selected' : '' }}>{{ $supplier->name }} ({{ $supplier->phone ?? '' }})</option>
                        @endforeach
                    </optgroup>
                </select>
                @error('person') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm font-medium">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20">
                        <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm font-medium">USD ($)</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4 p-3.5 bg-ink-50 dark:bg-ink-800/50 rounded-xl">
                <label class="form-label">{{ __('messages.tax_with_paren') }}GST/VAT)</label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[10px] font-semibold text-ink-500 dark:text-ink-400 mb-1 block">{{ __('messages.percentage') }}</label>
                        <input type="number" name="tax_rate" id="tax-rate" step="0.01" min="0" max="100" value="{{ old('tax_rate', $defaultTaxRate) }}" class="form-input">
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-ink-500 dark:text-ink-400 mb-1 block">{{ __('messages.type') }}</label>
                        <select name="tax_type" id="tax-type" class="form-select">
                            <option value="exclusive" {{ old('tax_type', $defaultTaxType) === 'exclusive' ? 'selected' : '' }}>{{ __('messages.extra_with_paren') }}Exclusive)</option>
                            <option value="inclusive" {{ old('tax_type', $defaultTaxType) === 'inclusive' ? 'selected' : '' }}>{{ __('messages.inclusive_with_paren') }}Inclusive)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.products') }}</label>
                <div id="products-container" class="space-y-2">
                    <div class="product-row flex items-center gap-2">
                        <select name="products[0][product_id]" required class="product-select form-select flex-1">
                            <option value=""> {{ __('messages.product') }} </option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-price="{{ $product->price }}" data-stock="{{ $product->stock }}">
                                    {{ $product->name }} ({{ __('messages.stock') }}: {{ $product->stock }}@if($product->unit) {{ $product->unit->short_name ?? $product->unit->name }}@endif)
                                </option>
                            @endforeach
                        </select>
                        <input type="number" name="products[0][unit_price]" min="0.01" step="0.01" value="" placeholder="{{ __('messages.price') }}" required class="product-price w-24 form-input text-center">
                        <input type="number" name="products[0][quantity]" min="1" value="1" required class="product-qty w-20 form-input text-center">
                        <input type="text" name="products[0][lot_number]" placeholder="{{ __('messages.lot_number') }}" class="product-lot w-24 form-input text-center" list="lot-suggestions">
                        <button type="button" class="remove-product p-2 text-danger-400 hover:text-danger-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <datalist id="lot-suggestions"></datalist>
                <p class="text-[10px] text-ink-400 mt-1" id="lot-hint"></p>
                <button type="button" id="add-product" class="text-primary-600 dark:text-primary-400 text-sm font-bold mt-2">+ {{ __('messages.another_product') }}</button>
            </div>

            <div class="bg-primary-50 dark:bg-primary-900/10 rounded-xl p-3.5 mb-4 space-y-1.5">
                <div class="flex justify-between text-sm">
                    <span class="text-ink-600 dark:text-ink-400">{{ __('messages.price_without_tax') }}:</span>
                    <span id="subtotal-amount" class="font-semibold text-ink-900 dark:text-ink-100">0 {{ __('messages.afn') }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-ink-600 dark:text-ink-400">{{ __('messages.tax') }}:</span>
                    <span id="tax-amount" class="font-semibold text-ink-900 dark:text-ink-100">0 {{ __('messages.afn') }}</span>
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

            const lots = productLots[select.value] || [];
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

        const taxRate = parseFloat(document.getElementById('tax-rate').value) || 0;
        const taxType = document.getElementById('tax-type').value;
        let taxAmount = 0;
        let total = subtotal;

        if (taxRate > 0) {
            if (taxType === 'exclusive') {
                taxAmount = subtotal * (taxRate / 100);
                total = subtotal + taxAmount;
            } else {
                total = subtotal;
                taxAmount = subtotal * (taxRate / (100 + taxRate));
                subtotal = total - taxAmount;
            }
        }

        document.getElementById('subtotal-amount').textContent = subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + symbol;
        document.getElementById('tax-amount').textContent = taxAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + symbol;
        document.getElementById('total-amount').textContent = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + symbol;
    }

    document.querySelectorAll('input[name="currency"]').forEach(radio => {
        radio.addEventListener('change', updateTotal);
    });

    document.getElementById('tax-rate').addEventListener('input', updateTotal);
    document.getElementById('tax-type').addEventListener('change', updateTotal);

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