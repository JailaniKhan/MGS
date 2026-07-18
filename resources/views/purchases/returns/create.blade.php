@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('purchases.returns.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_purchase_return') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('purchases.returns.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.purchase') }}</label>
                <select name="purchase_id" id="purchase-select" required class="form-input">
                    <option value="">-- {{ __('messages.select_purchase') }}</option>
                    @foreach ($purchases as $purchase)
                        <option value="{{ $purchase->id }}" data-currency="{{ $purchase->currency }}" data-supplier="{{ $purchase->party?->name ?? '' }}" @if(old('purchase_id') == $purchase->id) selected @endif>#{{ $purchase->id }} - {{ $purchase->party?->name ?? __('messages.unknown') }} ({{ number_format($purchase->total_amount) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }})</option>
                    @endforeach
                </select>
                @error('purchase_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.supplier') }}</label>
                <input type="text" id="supplier-name" readonly class="form-input bg-ink-100 dark:bg-ink-800 cursor-not-allowed">
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
                <div id="products-container" class="space-y-2">
                    <div class="product-row flex items-center gap-2">
                        <select name="products[0][product_id]" required class="product-select flex-1 form-input">
                            <option value="">{{ __('messages.product') }}</option>
                        </select>
                        <input type="number" name="products[0][quantity]" min="1" value="1" required class="product-qty w-20 form-input text-center">
                        <input type="number" name="products[0][unit_price]" min="0" step="0.01" value="0" required class="product-price w-24 form-input text-center">
                        <button type="button" class="remove-product text-danger-500 p-1 flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
                <button type="button" id="add-product" class="text-primary-600 dark:text-primary-400 text-xs font-semibold mt-2">+ {{ __('messages.another_product') }}</button>
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
    const purchasesData = @json($purchases->map(fn($p) => ['id' => $p->id, 'currency' => $p->currency, 'supplier' => $p->party?->name ?? '', 'items' => $p->purchaseItems->map(fn($i) => ['product_id' => $i->product_id, 'name' => $i->product->name . ($i->product->unit ? ' (' . ($i->product->unit->short_name ?? $i->product->unit->name) . ')' : ''), 'unit_price' => $i->unit_price, 'quantity' => $i->quantity])->values()->all()])->values()->all());
    let productIndex = 1;
    function getCurrencySymbol() { const p = purchasesData.find(p => p.id == document.getElementById('purchase-select').value); return p ? (p.currency === 'USD' ? '$' : '{{ __("messages.afn") }}') : '{{ __("messages.afn") }}'; }
    function updateProductOptions() {
        const sel = document.getElementById('purchase-select'), supp = document.getElementById('supplier-name'), selected = purchasesData.find(p => p.id == sel.value);
        if (selected) { supp.value = selected.supplier;
            document.querySelectorAll('.product-select').forEach(s => { const v = s.value; s.innerHTML = '<option value="">{{ __("messages.product") }}</option>'; selected.items.forEach(i => { const o = document.createElement('option'); o.value = i.product_id; o.textContent = i.name + ' ({{ __("messages.selected") }}: ' + i.quantity + ')'; o.dataset.price = i.unit_price; s.appendChild(o); }); if (v && selected.items.some(i => i.product_id == v)) s.value = v; });
        } else { supp.value = ''; document.querySelectorAll('.product-select').forEach(s => { s.innerHTML = '<option value="">{{ __("messages.product") }}</option>'; }); }
        updateTotal();
    }
    function updateTotal() { let t = 0; document.querySelectorAll('.product-row').forEach(r => { const s = r.querySelector('.product-select'), q = r.querySelector('.product-qty'), p = r.querySelector('.product-price'); if (s.value && q.value && p.value) t += parseFloat(p.value) * parseInt(q.value); }); document.getElementById('total-amount').textContent = t.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + getCurrencySymbol(); }
    document.getElementById('purchase-select').addEventListener('change', updateProductOptions);
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