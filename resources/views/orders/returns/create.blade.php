@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('orders.returns.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_order_return') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('orders.returns.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.order') }}</label>
                <select name="order_id" id="order-select" required class="form-input">
                    <option value="">-- {{ __('messages.select_order') }}</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}" data-currency="{{ $order->currency }}" data-customer="{{ $order->party?->name }}" @if(old('order_id') == $order->id) selected @endif>#{{ $order->id }} - {{ $order->party?->name }} ({{ number_format($order->total_amount) }} {{ $order->currency === 'USD' ? '$' : __('messages.afn') }})</option>
                    @endforeach
                </select>
                @error('order_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.customer') }}</label>
                <input type="text" id="customer-name" readonly class="form-input bg-ink-100 dark:bg-ink-800 cursor-not-allowed">
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.return_date') }}</label>
                <input type="date" name="return_date" value="{{ old('return_date', date('Y-m-d')) }}" required class="form-input">
                @error('return_date') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.return_reason') }}</label>
                <textarea name="reason" rows="2" class="form-input">{{ old('reason') }}</textarea>
                @error('reason') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.status') }}</label>
                <select name="status" required class="form-input">
                    <option value="pending" {{ old('status', 'pending') === 'pending' ? 'selected' : '' }}>{{ __('messages.pending') }}</option>
                    <option value="processing" {{ old('status') === 'processing' ? 'selected' : '' }}>{{ __('messages.processing') }}</option>
                    <option value="completed" {{ old('status', 'completed') === 'completed' ? 'selected' : '' }}>{{ __('messages.completed') }}</option>
                    <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>{{ __('messages.cancelled') }}</option>
                </select>
                @error('status') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.products') }}</label>
                <div id="products-container" class="space-y-2.5">
                    <div class="product-row rounded-xl border border-ink-100 dark:border-white/[0.06] bg-ink-50 dark:bg-white/[0.03] p-3">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <select name="products[0][product_id]" required class="product-select form-input">
                                    <option value="">{{ __('messages.product') }}</option>
                                </select>
                            </div>
                            <button type="button" class="remove-product w-11 h-11 rounded-lg bg-danger-50 dark:bg-danger-900/20 text-danger-500 flex items-center justify-center flex-shrink-0 transition-colors" aria-label="{{ __('messages.remove') }}">
                                <x-icon name="trash" class="w-4 h-4" strokeWidth="2"/>
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-2.5">
                            <div class="min-w-0">
                                <span class="row-label">{{ __('messages.quantity') }}</span>
                                <input type="number" name="products[0][quantity]" min="1" value="1" dir="ltr" inputmode="numeric" required class="product-qty w-full form-input text-center">
                            </div>
                            <div class="min-w-0">
                                <span class="row-label">{{ __('messages.price') }}</span>
                                <input type="number" name="products[0][unit_price]" min="0" step="0.01" value="0" dir="ltr" inputmode="decimal" required class="product-price w-full form-input text-center">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" id="add-product" class="inline-flex items-center gap-1.5 text-primary-600 dark:text-primary-400 text-xs font-semibold mt-3 active:scale-95 transition-transform"><x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2.2"/>{{ __('messages.another_product') }}</button>
            </div>

            <div class="bg-ink-50 dark:bg-ink-800/50 rounded-xl p-3 mb-4 flex justify-between text-sm">
                <span class="text-ink-500 dark:text-ink-400">{{ __('messages.total_amount') }}:</span>
                <span id="total-amount" class="font-bold text-ink-900 dark:text-white">0 {{ __('messages.afn') }}</span>
            </div>

            <button type="submit" class="btn-primary w-full">{{ __('messages.register') }}</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    const ordersData = @json($orders->map(fn($o) => ['id' => $o->id, 'currency' => $o->currency, 'customer' => $o->party?->name, 'items' => $o->orderItems->map(fn($i) => ['product_id' => $i->product_id, 'name' => $i->product->name . ($i->product->unit ? ' (' . ($i->product->unit->short_name ?? $i->product->unit->name) . ')' : ''), 'unit_price' => $i->unit_price, 'quantity' => $i->quantity])->values()->all()])->values()->all());
    let productIndex = 1;
    function getCurrencySymbol() { const o = ordersData.find(o => o.id == document.getElementById('order-select').value); return o ? (o.currency === 'USD' ? '$' : '{{ __("messages.afn") }}') : '{{ __("messages.afn") }}'; }
    function updateProductOptions() {
        const sel = document.getElementById('order-select'), cust = document.getElementById('customer-name'), selected = ordersData.find(o => o.id == sel.value);
        if (selected) { cust.value = selected.customer;
            document.querySelectorAll('.product-select').forEach(s => { const v = s.value; s.innerHTML = '<option value="">{{ __("messages.product") }}</option>'; selected.items.forEach(i => { const o = document.createElement('option'); o.value = i.product_id; o.textContent = i.name + ' ({{ __("messages.stock") }}: ' + i.quantity + ')'; o.dataset.price = i.unit_price; s.appendChild(o); }); if (v && selected.items.some(i => i.product_id == v)) s.value = v; });
        } else { cust.value = ''; document.querySelectorAll('.product-select').forEach(s => { s.innerHTML = '<option value="">{{ __("messages.product") }}</option>'; }); }
        updateTotal();
    }
    function updateTotal() { let t = 0; document.querySelectorAll('.product-row').forEach(r => { const s = r.querySelector('.product-select'), q = r.querySelector('.product-qty'), p = r.querySelector('.product-price'); if (s.value && q.value && p.value) t += parseFloat(p.value) * parseInt(q.value); }); document.getElementById('total-amount').textContent = t.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + getCurrencySymbol(); }
    document.getElementById('order-select').addEventListener('change', updateProductOptions);
    document.getElementById('add-product').addEventListener('click', function() {
        const c = document.getElementById('products-container'), f = c.querySelector('.product-row'), n = f.cloneNode(true);
        n.querySelector('.product-select').name = 'products[' + productIndex + '][product_id]'; n.querySelector('.product-select').value = '';
        n.querySelector('.product-qty').name = 'products[' + productIndex + '][quantity]'; n.querySelector('.product-qty').value = 1;
        n.querySelector('.product-price').name = 'products[' + productIndex + '][unit_price]'; n.querySelector('.product-price').value = 0;
        n.querySelector('.product-select').addEventListener('change', updateTotal);
        n.querySelector('.product-qty').addEventListener('input', updateTotal);
        n.querySelector('.product-price').addEventListener('input', updateTotal);
        n.querySelector('.remove-product').addEventListener('click', function() { if (c.querySelectorAll('.product-row').length > 1) { n.remove(); updateTotal(); } });
        c.appendChild(n); productIndex++;
    });
    document.querySelectorAll('.product-select, .product-qty, .product-price').forEach(el => el.addEventListener('change', updateTotal));
    document.querySelectorAll('.product-qty, .product-price').forEach(el => el.addEventListener('input', updateTotal));
    document.querySelectorAll('.remove-product').forEach(btn => btn.addEventListener('click', function() { const c = document.getElementById('products-container'); if (c.querySelectorAll('.product-row').length > 1) { this.closest('.product-row').remove(); updateTotal(); } }));
    updateProductOptions(); updateTotal();
</script>
@endpush