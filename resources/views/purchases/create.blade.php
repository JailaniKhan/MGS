@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 dark:text-ink-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}</a>
    </div>
    <h2 class="text-lg font-semibold mb-4">{{ __('messages.new_purchase') }}</h2>

    <div class="card p-5">
        <form action="{{ route('purchases.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ __('messages.select_party') }}</label>
                <select name="person" required
                    class="w-full rounded-xl border border-ink-200 dark:border-ink-700 bg-ink-50/50 dark:bg-ink-800/50 text-ink-900 dark:text-ink-100 px-4 py-2.5 text-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 placeholder:text-ink-400 dark:placeholder:text-ink-500">
                    <option value="">-- {{ __('messages.select_party') }}</option>
                    <optgroup label="{{ __('messages.suppliers') }}">
                        @foreach ($suppliers as $supplier)
                            <option value="supplier:{{ $supplier->id }}" {{ old('person') == 'supplier:'.$supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="{{ __('messages.customers') }}">
                        @foreach ($customers as $customer)
                            <option value="customer:{{ $customer->id }}" {{ old('person') == 'customer:'.$customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
                @error('person') <p class="text-danger-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">{{ __('messages.currency_unit') }}</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2 border border-ink-200 dark:border-ink-700 rounded-lg cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="text-primary-600 dark:text-primary-400">
                        <span class="text-sm">{{ __('messages.afn') }}اني ({{ __('messages.afn') }})</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 border border-ink-200 dark:border-ink-700 rounded-lg cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20">
                        <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="text-primary-600 dark:text-primary-400">
                        <span class="text-sm">{{ __('messages.usd_with_paren') }}$)</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Tax Section -->
            <div class="mb-4 p-3 bg-ink-50 dark:bg-ink-700 rounded-lg">
                <label class="block text-sm font-medium mb-2">{{ __('messages.tax_with_paren') }}GST/VAT)</label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-ink-500 dark:text-ink-400 mb-1">{{ __('messages.percentage') }}</label>
                        <input type="number" name="tax_rate" id="tax-rate" step="0.01" min="0" max="100" value="{{ old('tax_rate', $defaultTaxRate) }}"
                            class="w-full rounded-xl border border-ink-200 dark:border-ink-700 bg-ink-50/50 dark:bg-ink-800/50 text-ink-900 dark:text-ink-100 px-4 py-2.5 text-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 placeholder:text-ink-400 dark:placeholder:text-ink-500">
                    </div>
                    <div>
                        <label class="block text-xs text-ink-500 dark:text-ink-400 mb-1">{{ __('messages.type') }}</label>
                        <select name="tax_type" id="tax-type"
                            class="w-full rounded-xl border border-ink-200 dark:border-ink-700 bg-ink-50/50 dark:bg-ink-800/50 text-ink-900 dark:text-ink-100 px-4 py-2.5 text-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 placeholder:text-ink-400 dark:placeholder:text-ink-500">
                            <option value="exclusive" {{ old('tax_type', $defaultTaxType) === 'exclusive' ? 'selected' : '' }}>{{ __('messages.extra_with_paren') }}Exclusive)</option>
                            <option value="inclusive" {{ old('tax_type', $defaultTaxType) === 'inclusive' ? 'selected' : '' }}>{{ __('messages.inclusive_with_paren') }}Inclusive)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">{{ __('messages.products') }}</label>
                <div id="products-container">
                    <div class="product-row flex items-center gap-2 mb-2">
                        <select name="products[0][product_id]" required
                            class="product-select flex-1 px-3 py-2 border border-ink-200 dark:border-ink-700 rounded-xl bg-ink-50/50 dark:bg-ink-800/50 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500">
                            <option value=""> {{ __('messages.product') }} </option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-price="{{ $product->price }}" data-stock="{{ $product->stock }}">
                                    {{ $product->name }} @if($product->unit)({{ $product->unit->short_name ?? $product->unit->name }})@endif
                                </option>
                            @endforeach
                        </select>
                        <input type="number" name="products[0][quantity]" min="1" value="1" required placeholder="{{ __('messages.quantity') }}"
                            class="product-qty w-20 px-3 py-2 border border-ink-200 dark:border-ink-700 rounded-xl bg-ink-50/50 dark:bg-ink-800/50 text-sm text-center focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500">
                        <input type="number" name="products[0][unit_price]" min="0" step="0.01" value="0" required placeholder="{{ __('messages.price') }}"
                            class="product-price w-24 px-3 py-2 border border-ink-200 dark:border-ink-700 rounded-xl bg-ink-50/50 dark:bg-ink-800/50 text-sm text-center focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500">
                        <input type="text" name="products[0][lot_number]" placeholder="{{ __('messages.lot_number') }}"
                            class="product-lot w-24 px-3 py-2 border border-ink-200 dark:border-ink-700 rounded-xl bg-ink-50/50 dark:bg-ink-800/50 text-sm text-center focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500">
                        <button type="button" class="remove-product text-danger-500 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="button" id="add-product" class="text-primary-600 dark:text-primary-400 text-sm mt-2">+ {{ __('messages.another_product') }}</button>
            </div>

            <div class="bg-ink-50 dark:bg-ink-700 rounded-lg p-3 mb-4">
                <div class="flex justify-between text-sm">
                    <span>{{ __('messages.total_amount') }}:</span>
                    <span id="total-amount" class="font-bold">0 {{ __('messages.afn') }}</span>
                </div>
            </div>

            <button type="submit" class="w-full bg-[#0d9488] text-white py-3 rounded-lg font-medium">{{ __('messages.complete_purchase') }}</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    let productIndex = 1;

    function getCurrencySymbol() {
        return document.querySelector('input[name="currency"]:checked')?.value === 'USD' ? '$' : __('messages.afn');
    }

    function updateTotal() {
        let total = 0;
        const symbol = getCurrencySymbol();
        document.querySelectorAll('.product-row').forEach(row => {
            const qty = row.querySelector('.product-qty');
            const price = row.querySelector('.product-price');
            if (qty.value && price.value) {
                total += parseFloat(price.value) * parseInt(qty.value);
            }
        });
        document.getElementById('total-amount').textContent = total.toLocaleString() + ' ' + symbol;
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
        newRow.querySelector('.product-price').value = 0;
        newRow.querySelector('.product-lot').name = `products[${productIndex}][lot_number]`;
        newRow.querySelector('.product-lot').value = '';

        newRow.querySelector('.product-qty').addEventListener('input', updateTotal);
        newRow.querySelector('.product-price').addEventListener('input', updateTotal);
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