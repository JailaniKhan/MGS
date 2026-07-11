@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('payments.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-2">{{ __('messages.new_payment') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('payments.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.order') }}</label>
                <select name="order_id" id="order-select" required class="form-input">
                    <option value="">-- {{ __('messages.select_order') }}</option>
                    @foreach ($orders as $order)
                         <option value="{{ $order->id }}" data-remaining="{{ $order->remaining_amount }}" data-total="{{ $order->total_amount }}" data-customer="{{ $order->party?->name }}" data-currency="{{ $order->currency }}" {{ (old('order_id') == $order->id || $selectedOrderId == $order->id) ? 'selected' : '' }}>{{ __('messages.order') }} #{{ $order->id }} - {{ $order->party?->name }} ({{ __('messages.pending') }}: {{ number_format($order->remaining_amount) }} {{ $order->currency === 'USD' ? '$' : __('messages.afn') }})</option>
                    @endforeach
                </select>
                @error('order_id') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div id="order-info" class="hidden mb-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3 text-xs">
                <div class="flex justify-between mb-1"><span class="text-gray-500 dark:text-gray-400">{{ __('messages.customer') }}:</span><span id="info-customer" class="font-medium text-gray-800 dark:text-gray-200"></span></div>
                <div class="flex justify-between mb-1"><span class="text-gray-500 dark:text-gray-400">{{ __('messages.order_total') }}:</span><span id="info-total" class="font-medium text-gray-800 dark:text-gray-200"></span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">{{ __('messages.remaining_amount') }}:</span><span id="info-remaining" class="font-bold text-primary-600 dark:text-primary-400"></span></div>
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('messages.usd_with_paren') }}$)</span>
                    </label>
                </div>
                @error('currency') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.payment_amount') }}</label>
                <input type="number" name="amount" id="amount-input" value="{{ old('amount') }}" step="0.01" min="0.01" required class="form-input">
                @error('amount') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.notes_optional') }}</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="form-input">
                @error('notes') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn-primary w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ __('messages.record_payment') }}</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    const orderSelect = document.getElementById('order-select');
    const orderInfo = document.getElementById('order-info');
    const infoCustomer = document.getElementById('info-customer');
    const infoTotal = document.getElementById('info-total');
    const infoRemaining = document.getElementById('info-remaining');
    const amountInput = document.getElementById('amount-input');
    orderSelect.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if (option.value) {
            infoCustomer.textContent = option.dataset.customer;
            infoTotal.textContent = parseFloat(option.dataset.total).toLocaleString() + ' ' + (option.dataset.currency === 'USD' ? '$' : '{{ __("messages.afn") }}');
            infoRemaining.textContent = parseFloat(option.dataset.remaining).toLocaleString() + ' ' + (option.dataset.currency === 'USD' ? '$' : '{{ __("messages.afn") }}');
            orderInfo.classList.remove('hidden');
            amountInput.max = option.dataset.remaining;
            document.querySelector(`input[name="currency"][value="${option.dataset.currency}"]`).checked = true;
        } else {
            orderInfo.classList.add('hidden');
            amountInput.max = '';
        }
    });
    if (orderSelect.value) orderSelect.dispatchEvent(new Event('change'));
</script>
@endpush