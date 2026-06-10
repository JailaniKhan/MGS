@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('payments.index') }}" class="text-gray-500 dark:text-gray-400 text-sm">&larr; بېرته</a>
    </div>
    <h2 class="text-lg font-semibold mb-4">نوې پیسې ثبتول</h2>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <form action="{{ route('payments.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">امر</label>
                <select name="order_id" id="order-select" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                    <option value="">-- امر انتخاب کړئ --</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}" data-remaining="{{ $order->remaining_amount }}" data-total="{{ $order->total_amount }}" data-customer="{{ $order->customer->name }}" 
                            {{ (old('order_id') == $order->id || $selectedOrderId == $order->id) ? 'selected' : '' }}>
                            امر #{{ $order->id }} - {{ $order->customer->name }} (پاتې: {{ number_format($order->remaining_amount) }} افغ)
                        </option>
                    @endforeach
                </select>
                @error('order_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div id="order-info" class="hidden mb-4 bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-sm">
                <div class="flex justify-between mb-1">
                    <span>ګیراک:</span>
                    <span id="info-customer" class="font-medium"></span>
                </div>
                <div class="flex justify-between mb-1">
                    <span>د امر ټوله بیه:</span>
                    <span id="info-total" class="font-medium"></span>
                </div>
                <div class="flex justify-between">
                    <span>پاتې پیسې:</span>
                    <span id="info-remaining" class="font-bold text-[#f53003]"></span>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">د پیسو اندازه (افغ)</label>
                <input type="number" name="amount" id="amount-input" value="{{ old('amount') }}" step="0.01" min="0.01" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">یادښت (اختیاري)</label>
                <input type="text" name="notes" value="{{ old('notes') }}"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#f53003] focus:border-transparent">
                @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="w-full bg-[#f53003] text-white py-3 rounded-lg font-medium">پیسې ثبتول</button>
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
            const remaining = parseFloat(option.dataset.remaining);
            const total = parseFloat(option.dataset.total);
            const customer = option.dataset.customer;
            
            infoCustomer.textContent = customer;
            infoTotal.textContent = total.toLocaleString() + ' افغ';
            infoRemaining.textContent = remaining.toLocaleString() + ' افغ';
            orderInfo.classList.remove('hidden');
            
            amountInput.max = remaining;
        } else {
            orderInfo.classList.add('hidden');
            amountInput.max = '';
        }
    });

    // Trigger if old value exists
    if (orderSelect.value) {
        orderSelect.dispatchEvent(new Event('change'));
    }
</script>
@endpush