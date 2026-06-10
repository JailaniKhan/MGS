@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('customers.index') }}" class="text-gray-500 dark:text-gray-400 text-sm">&larr; بېرته</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <h2 class="text-lg font-semibold mb-2">{{ $customer->name }}</h2>
        @if ($customer->phone)
            <p class="text-sm text-gray-600 dark:text-gray-400">تلیفون: {{ $customer->phone }}</p>
        @endif
        @if ($customer->address)
            <p class="text-sm text-gray-600 dark:text-gray-400">پته: {{ $customer->address }}</p>
        @endif
        <div class="flex gap-2 mt-3">
            <a href="{{ route('customers.edit', $customer) }}" class="text-blue-600 dark:text-blue-400 text-sm">سمول</a>
            <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('آیا ډاډه یاست؟')">
                @csrf @method('DELETE')
                <button class="text-red-600 dark:text-red-400 text-sm">ړنګول</button>
            </form>
        </div>
    </div>

    <h3 class="font-semibold text-sm mb-3">امرونه</h3>
    @forelse ($customer->orders as $order)
        <a href="{{ route('orders.show', $order) }}" class="block bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-2">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-sm font-medium">امر #{{ $order->id }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">{{ $order->created_at->format('Y/m/d') }}</span>
                </div>
                <div class="text-left">
                    <div class="text-sm font-semibold">{{ number_format($order->total_amount) }} افغ</div>
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
        </a>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">د دې ګیراک لپاره امر نشته</p>
    @endforelse
@endsection