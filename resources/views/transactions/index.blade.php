@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">معاملې</h2>
    </div>
    <!-- Tabs -->
    <div class="flex gap-2 mb-4">
        <button id="tab-orders" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg bg-[#f53003] text-white" onclick="switchTab('orders')">
            امرونه
        </button>
        <button id="tab-purchases" class="tab-btn px-4 py-2 text-sm font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300" onclick="switchTab('purchases')">
            خریدنې
        </button>
    </div>

    <!-- Orders List -->
    <div id="section-orders" class="tab-section">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">امرونه</h3>
            <a href="{{ route('orders.create') }}" class="bg-[#f53003] text-white px-4 py-2 rounded-lg text-sm font-medium">
                + نوی امر
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            @forelse ($orders as $order)
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <a href="{{ route('orders.show', $order) }}" class="block">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-medium">امر #{{ $order->id }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">{{ $order->customer->name }}</span>
                            </div>
                            <div class="text-left">
                                <div class="text-sm font-semibold">{{ number_format($order->total_amount) }} {{ $order->currency === 'USD' ? '$' : 'افغ' }}</div>
                                <span class="inline-block text-xs px-2 py-0.5 rounded-full 
                                    @if($order->status === 'completed') bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300
                                    @elseif($order->status === 'processing') bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300
                                    @elseif($order->status === 'cancelled') bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300
                                    @else bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 @endif">
                                    @switch($order->status)
                                        @case('completed') بشپړ @break
                                        @case('processing') پروسس @break
                                        @case('cancelled') لغوه @break
                                        @default پاتې
                                    @endswitch
                                </span>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $order->created_at->format('Y/m/d H:i') }}</div>
                    </a>
                    <!-- @if (!$order->is_fully_paid && $order->status !== 'cancelled')
                        <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                            <a href="{{ route('payments.create') }}?order_id={{ $order->id }}" 
                               class="text-[#0d9488] dark:text-teal-400 text-sm font-medium hover:underline">
                                + پیسې ورکول
                            </a>
                            <span class="text-xs text-gray-400 dark:text-gray-500 mr-3">
                                پاتې: {{ number_format($order->remaining_amount) }} افغ
                            </span>
                        </div>
                    @endif -->
                </div>
            @empty
                <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                    لا تر اوسه امر نشته
                </div>
            @endforelse
        </div>
    </div>

    <!-- Purchases List -->
    <div id="section-purchases" class="tab-section hidden">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">خریدنې</h3>
            <a href="{{ route('purchases.create') }}" class="bg-[#f53003] text-white px-4 py-2 rounded-lg text-sm font-medium">
                + نوی خرید
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            @forelse ($purchases as $purchase)
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <a href="{{ route('purchases.show', $purchase) }}" class="block">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-medium">خرید #{{ $purchase->id }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">{{ $purchase->supplier->name }}</span>
                            </div>
                            <div class="text-left">
                                <div class="text-sm font-semibold">{{ number_format($purchase->total_amount) }} {{ $purchase->currency === 'USD' ? '$' : 'افغ' }}</div>
                                <span class="inline-block text-xs px-2 py-0.5 rounded-full 
                                    @if($purchase->status === 'completed') bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300
                                    @elseif($purchase->status === 'processing') bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300
                                    @elseif($purchase->status === 'cancelled') bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300
                                    @else bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 @endif">
                                    @switch($purchase->status)
                                        @case('completed') بشپړ @break
                                        @case('processing') پروسس @break
                                        @case('cancelled') لغوه @break
                                        @default پاتې
                                    @endswitch
                                </span>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $purchase->created_at->format('Y/m/d H:i') }}</div>
                    </a>
                    <!-- @if (!$purchase->is_fully_paid && $purchase->status !== 'cancelled')
                        <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                            <a href="{{ route('purchases.show', $purchase) }}" 
                               class="text-[#0d9488] dark:text-teal-400 text-sm font-medium hover:underline">
                                + پیسې ورکول
                            </a>
                            <span class="text-xs text-gray-400 dark:text-gray-500 mr-3">
                                پاتې: {{ number_format($purchase->remaining_amount) }} {{ $purchase->currency === 'USD' ? '$' : 'افغ' }}
                            </span>
                        </div>
                    @endif -->
                </div>
            @empty
                <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                    لا تر اوسه خرید نشته
                </div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function switchTab(tab) {
        // Hide all sections
        document.querySelectorAll('.tab-section').forEach(el => el.classList.add('hidden'));
        // Show selected section
        document.getElementById('section-' + tab).classList.remove('hidden');

        // Update button styles
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('bg-[#f53003]', 'text-white');
            el.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-600', 'dark:text-gray-300');
        });
        const activeBtn = document.getElementById('tab-' + tab);
        activeBtn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-600', 'dark:text-gray-300');
        activeBtn.classList.add('bg-[#f53003]', 'text-white');
    }
</script>
@endpush