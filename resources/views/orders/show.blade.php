@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('orders.index') }}" class="text-gray-500 dark:text-gray-400 text-sm">&larr; بېرته</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <div class="flex justify-between items-start mb-3">
            <div>
                <h2 class="text-lg font-semibold">امر #{{ $order->id }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $order->created_at->format('Y/m/d H:i') }}</p>
            </div>
            <span class="inline-block text-sm px-3 py-1 rounded-full 
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

        <div class="text-sm mb-3">
            <span class="text-gray-500 dark:text-gray-400">ګیراک: </span>
            <a href="{{ route('customers.show', $order->customer) }}" class="font-medium text-[#0d9488] dark:text-teal-400">{{ $order->customer->name }}</a>
        </div>

        <div class="text-sm mb-1">
            <span class="text-gray-500 dark:text-gray-400">د پیسو واحد: </span>
            <span class="font-medium">{{ $order->currency === 'USD' ? 'ډالر ($)' : 'افغاني (افغ)' }}</span>
        </div>

        @if ($order->customer->phone)
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">تلیفون: {{ $order->customer->phone }}</p>
        @endif
        @if ($order->customer->address)
            <p class="text-xs text-gray-500 dark:text-gray-400">پته: {{ $order->customer->address }}</p>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-sm">
            د امر توکي
        </div>
        @foreach ($order->orderItems as $item)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div>
                    <div class="text-sm font-medium">{{ $item->product->name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $item->quantity }} x {{ number_format($item->unit_price) }} {{ $order->currency === 'USD' ? '$' : 'افغ' }}
                    </div>
                </div>
                <div class="text-sm font-semibold">{{ number_format($item->subtotal) }} {{ $order->currency === 'USD' ? '$' : 'افغ' }}</div>
            </div>
        @endforeach
        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700 font-bold">
            <span>ټوله</span>
            <span>{{ number_format($order->total_amount) }} {{ $order->currency === 'USD' ? '$' : 'افغ' }}</span>
        </div>
    </div>

    <!-- Payment Summary -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <h3 class="font-semibold text-sm mb-3">د پیسو ورکړه</h3>
        <div class="space-y-2 text-sm mb-3">
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">ټوله بیه:</span>
                <span class="font-medium">{{ number_format($order->total_amount) }} {{ $order->currency === 'USD' ? '$' : 'افغ' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">ورکړل شوي:</span>
                <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($order->paid_amount) }} {{ $order->currency === 'USD' ? '$' : 'افغ' }}</span>
            </div>
            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2">
                <span class="font-semibold">پاتې پیسې:</span>
                <span class="font-bold {{ $order->is_fully_paid ? 'text-green-600 dark:text-green-400' : 'text-[#0d9488]' }}">
                    {{ $order->is_fully_paid ? 'بشپړ شوی' : number_format($order->remaining_amount) . ' ' . ($order->currency === 'USD' ? '$' : 'افغ') }}
                </span>
            </div>
        </div>

        @if ($order->payments->count() > 0)
            <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mb-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">د پیسو تاریخچه:</p>
                @foreach ($order->payments as $payment)
                    <div class="flex justify-between items-center py-1.5 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                        <div>
                            <span class="text-xs">{{ $payment->created_at->format('Y/m/d H:i') }}</span>
                            @if ($payment->notes)
                                <span class="text-xs text-gray-400 dark:text-gray-500 mr-1">({{ $payment->notes }})</span>
                            @endif
                        </div>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">{{ number_format($payment->amount) }} {{ $payment->currency === 'USD' ? '$' : 'افغ' }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if (!$order->is_fully_paid && $order->status !== 'cancelled')
            <a href="{{ route('payments.create') }}?order_id={{ $order->id }}" 
               class="block w-full text-center bg-[#0d9488] text-white py-2 rounded-lg text-sm font-medium">
                + پیسې ورکول
            </a>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="font-semibold text-sm mb-3">د حالت بدلول</h3>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('orders.status', [$order, 'pending']) }}" 
                class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium {{ $order->status === 'pending' ? 'bg-yellow-500 text-white' : 'bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300' }}">
                پاتې
            </a>
            <a href="{{ route('orders.status', [$order, 'processing']) }}"
                class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium {{ $order->status === 'processing' ? 'bg-blue-500 text-white' : 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' }}">
                پروسس
            </a>
            <a href="{{ route('orders.status', [$order, 'completed']) }}"
                class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium {{ $order->status === 'completed' ? 'bg-green-500 text-white' : 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' }}">
                بشپړ
            </a>
            <a href="{{ route('orders.status', [$order, 'cancelled']) }}"
                class="flex-1 text-center px-3 py-2 rounded-lg text-sm font-medium {{ $order->status === 'cancelled' ? 'bg-red-500 text-white' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' }}"
                onclick="return confirm('آیا ډاډه یاست چې غواړئ دا امر لغوه کړئ؟')">
                لغوه
            </a>
        </div>
    </div>

    @if ($order->status !== 'cancelled')
        <form action="{{ route('orders.destroy', $order) }}" method="POST" class="mt-4" onsubmit="return confirm('آیا ډاډه یاست چې غواړئ دا امر ړنګ کړئ؟')">
            @csrf @method('DELETE')
            <button class="w-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 py-3 rounded-lg text-sm font-medium">
                امر ړنګول
            </button>
        </form>
    @endif
@endsection