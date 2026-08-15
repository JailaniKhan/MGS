@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('payments.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_payment') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('payments.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label class="form-label">{{ __('messages.payment_type') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 px-3 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="type" value="order" data-type="order" {{ old('type', $selectedType) === 'order' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.order_payment') }}</span>
                    </label>
                    <label class="flex items-center gap-2 px-3 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="type" value="purchase" data-type="purchase" {{ old('type', $selectedType) === 'purchase' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.purchase_payment') }}</span>
                    </label>
                </div>
                @error('type') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label" id="document-label">{{ $selectedType === 'purchase' ? __('messages.purchase') : __('messages.order') }}</label>

                <select name="order_id" id="order-select" required class="form-input" data-type="order">
                    <option value="">-- {{ __('messages.select_order') }}</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}" data-remaining="{{ $order->remaining }}" data-total="{{ $order->total_amount }}" data-party="{{ $order->party?->name }}" data-currency="{{ $order->currency }}" {{ (old('order_id') == $order->id || $selectedOrderId == $order->id) ? 'selected' : '' }}>{{ __('messages.order') }} #{{ $order->id }} - {{ $order->party?->name }} ({{ __('messages.pending') }}: {{ number_format($order->remaining) }} {{ $order->currency === 'USD' ? '$' : __('messages.afn') }})</option>
                    @endforeach
                </select>

                <select name="purchase_id" id="purchase-select" class="form-input" data-type="purchase">
                    <option value="">-- {{ __('messages.select_purchase') }}</option>
                    @foreach ($purchases as $purchase)
                        <option value="{{ $purchase->id }}" data-remaining="{{ $purchase->remaining }}" data-total="{{ $purchase->total_amount }}" data-party="{{ $purchase->party?->name }}" data-currency="{{ $purchase->currency }}" {{ (old('purchase_id') == $purchase->id || $selectedPurchaseId == $purchase->id) ? 'selected' : '' }}>{{ __('messages.purchase') }} #{{ $purchase->id }} - {{ $purchase->party?->name }} ({{ __('messages.pending') }}: {{ number_format($purchase->remaining) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }})</option>
                    @endforeach
                </select>

                @error('order_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                @error('purchase_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div id="document-info" class="hidden mb-4 bg-ink-50 dark:bg-ink-800/50 rounded-lg p-3 text-xs">
                <div class="flex justify-between mb-1"><span id="party-label" class="text-ink-500 dark:text-ink-400" data-customer="{{ __('messages.customer') }}" data-supplier="{{ __('messages.supplier') }}">{{ $selectedType === 'purchase' ? __('messages.supplier') : __('messages.customer') }}</span><span id="info-party" class="font-medium text-ink-800 dark:text-ink-200"></span></div>
                <div class="flex justify-between mb-1"><span class="text-ink-500 dark:text-ink-400">{{ __('messages.order_total') }}:</span><span id="info-total" class="font-medium text-ink-800 dark:text-ink-200"></span></div>
                <div class="flex justify-between"><span class="text-ink-500 dark:text-ink-400">{{ __('messages.remaining_amount') }}:</span><span id="info-remaining" class="font-bold text-primary-600 dark:text-primary-400"></span></div>
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.usd_with_paren') }}$)</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.payment_amount') }}</label>
                <input type="number" name="amount" id="amount-input" value="{{ old('amount') }}" step="0.01" min="0.01" required class="form-input">
                @error('amount') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.notes_optional') }}</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="form-input">
                @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.record_payment') }}</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const typeInputs = Array.prototype.slice.call(document.querySelectorAll('input[name="type"]'));
        const orderSelect = document.getElementById('order-select');
        const purchaseSelect = document.getElementById('purchase-select');
        const infoBox = document.getElementById('document-info');
        const partyLabel = document.getElementById('party-label');
        const infoParty = document.getElementById('info-party');
        const infoTotal = document.getElementById('info-total');
        const infoRemaining = document.getElementById('info-remaining');
        const amountInput = document.getElementById('amount-input');

        function activeType() {
            const checked = typeInputs.find(function (input) { return input.checked; });
            return checked ? checked.dataset.type : 'order';
        }

        function fillInfo(select, type) {
            const option = select.options[select.selectedIndex];
            if (!option.value) {
                infoBox.classList.add('hidden');
                amountInput.max = '';
                return;
            }
            partyLabel.textContent = type === 'purchase' ? partyLabel.dataset.supplier : partyLabel.dataset.customer;
            infoParty.textContent = option.dataset.party || '';
            infoTotal.textContent = parseFloat(option.dataset.total).toLocaleString() + ' ' + (option.dataset.currency === 'USD' ? '$' : '{{ __("messages.afn") }}');
            infoRemaining.textContent = parseFloat(option.dataset.remaining).toLocaleString() + ' ' + (option.dataset.currency === 'USD' ? '$' : '{{ __("messages.afn") }}');
            infoBox.classList.remove('hidden');
            amountInput.max = option.dataset.remaining;
            const currencyInput = document.querySelector('input[name="currency"][value="' + option.dataset.currency + '"]');
            if (currencyInput) currencyInput.checked = true;
        }

        function refresh() {
            const type = activeType();
            const isPurchase = type === 'purchase';
            orderSelect.style.display = isPurchase ? 'none' : '';
            purchaseSelect.style.display = isPurchase ? '' : 'none';
            orderSelect.required = !isPurchase;
            purchaseSelect.required = isPurchase;
            document.getElementById('document-label').textContent = isPurchase ? purchaseSelect.dataset.label : orderSelect.dataset.label;
            const active = isPurchase ? purchaseSelect : orderSelect;
            fillInfo(active, type);
        }

        orderSelect.dataset.label = '{{ __("messages.order") }}';
        purchaseSelect.dataset.label = '{{ __("messages.purchase") }}';

        typeInputs.forEach(function (input) {
            input.addEventListener('change', refresh);
        });
        orderSelect.addEventListener('change', function () { fillInfo(orderSelect, 'order'); });
        purchaseSelect.addEventListener('change', function () { fillInfo(purchaseSelect, 'purchase'); });

        refresh();
    })();
</script>
@endpush