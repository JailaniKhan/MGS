@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">امرونه</h2>
        <a href="{{ route('orders.create') }}" class="bg-[#f53003] text-white px-4 py-2 rounded-lg text-sm font-medium">
            + نوی امر
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse ($orders as $order)
            <a href="{{ route('orders.show', $order) }}" class="block px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium">امر #{{ $order->id }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ $order->customer->name }}</span>
                        @if ($order->remaining_amount <= 0 && $order->status !== 'cancelled')
                            <span class="text-xs px-1.5 py-0.5 rounded-full bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300">بشپړ شوی</span>
                        @endif
                    </div>
                    <div class="text-left">
                        <div class="text-sm font-semibold">{{ number_format($order->total_amount) }} {{ $order->currency === 'USD' ? '$' : 'افغ' }}</div>
                        @if ($order->status !== 'cancelled')
                            <div class="text-xs {{ $order->remaining_amount > 0 ? 'text-[#0d9488]' : 'text-green-600 dark:text-green-400' }}">
                                @if ($order->remaining_amount > 0)
                                    پاتې: {{ number_format($order->remaining_amount) }} {{ $order->currency === 'USD' ? '$' : 'افغ' }}
                                @else
                                    ټول ورکړل شوی
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                <div class="flex items-center justify-between mt-1">
                    <div class="text-xs text-gray-400 dark:text-gray-500">{{ $order->created_at->format('Y/m/d H:i') }}</div>
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
            </a>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه امر نشته
            </div>
        @endforelse
    </div>
@endsection