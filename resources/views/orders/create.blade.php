@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('orders.index') }}" class="text-gray-500 dark:text-gray-400 text-sm">&larr; بېرته</a>
    </div>
    <h2 class="text-lg font-semibold mb-4">نوی امر</h2>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <form action="{{ route('orders.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">ګیراک</label>
                <select name="customer_id" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                    <option value="">-- ګیراک انتخاب کړئ --</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }} ({{ $customer->phone }})</option>
                    @endforeach
                </select>
                @error('customer_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">د پیسو واحد</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer has-[:checked]:border-[#0d9488] has-[:checked]:bg-teal-50 dark:has-[:checked]:bg-teal-900/20">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="text-[#0d9488]">
                        <span class="text-sm">افغاني (افغ)</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer has-[:checked]:border-[#0d9488] has-[:checked]:bg-teal-50 dark:has-[:checked]:bg-teal-900/20">
                        <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="text-[#0d9488]">
                        <span class="text-sm">ډالر ($)</span>
                    </label>
                </div>
                @error('currency') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">محصولات</label>
                <div id="products-container">
                    <div class="product-row flex items-center gap-2 mb-2">
                        <select name="products[0][product_id]" required
                            class="product-select flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#0d9488] focus:border-transparent">
                            <option value=""> محصول </option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-price="{{ $product->price }}" data-stock="{{ $product->stock }}">
                                    {{ $product->name }} (موجودي: {{ $product->stock }}@if($product->unit) {{ $product->unit->short_name ?? $product->unit->name }}@endif)
                                </option>
                            @endforeach
                        </select>
                        <input type="number" name="products[0][quantity]" min="1" value="1" required
                            class="product-qty w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-center focus:ring-2 focus:ring-[#0d9488] focus:border-transparent">
                        <button type="button" class="remove-product text-red-500 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="button" id="add-product" class="text-[#0d9488] text-sm mt-2">+ بل محصول</button>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 mb-4">
                <div class="flex justify-between text-sm">
                    <span>ټوله بیه:</span>
                    <span id="total-amount" class="font-bold">0 افغ</span>
                </div>
            </div>

            <button type="submit" class="w-full bg-[#0d9488] text-black py-3 rounded-lg font-medium">امر جوړول</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    let productIndex = 1;

    function getCurrencySymbol() {
        return document.querySelector('input[name="currency"]:checked')?.value === 'USD' ? '$' : 'افغ';
    }

    function updateTotal() {
        let total = 0;
        const symbol = getCurrencySymbol();
        document.querySelectorAll('.product-row').forEach(row => {
            const select = row.querySelector('.product-select');
            const qty = row.querySelector('.product-qty');
            if (select.value && qty.value) {
                const price = select.options[select.selectedIndex].dataset.price || 0;
                total += parseFloat(price) * parseInt(qty.value);
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
        
        newRow.querySelector('.product-select').addEventListener('change', updateTotal);
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
        select.addEventListener('change', updateTotal);
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