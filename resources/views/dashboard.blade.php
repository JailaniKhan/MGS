@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">ټول ګیراکان</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $totalCustomers }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">ټول محصولات</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $totalProducts }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">ټول امرونه</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $totalOrders }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">ټولې ترلاسه شوې پیسې</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($totalRevenue) }} افغ</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="bg-yellow-50 dark:bg-yellow-900/30 rounded-xl p-4 shadow-sm border border-yellow-200 dark:border-yellow-700">
            <div class="text-xs text-yellow-700 dark:text-yellow-400 mb-1">د پاملرنې وړ امرونه</div>
            <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-300">{{ $pendingOrders }}</div>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-xl p-4 shadow-sm border border-blue-200 dark:border-blue-700">
            <div class="text-xs text-blue-700 dark:text-blue-400 mb-1">په پروسس کې</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-300">{{ $processingOrders }}</div>
        </div>
        <div class="bg-orange-50 dark:bg-orange-900/30 rounded-xl p-4 shadow-sm border border-orange-200 dark:border-orange-700">
            <div class="text-xs text-orange-700 dark:text-orange-400 mb-1">پاتې پیسې لري</div>
            <div class="text-2xl font-bold text-orange-600 dark:text-orange-300">{{ $pendingPayments }}</div>
        </div>
    </div>

    @if ($lowStockProducts > 0)
        <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-xl p-4 mb-6">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                <span class="text-sm text-red-700 dark:text-red-300"><strong>{{ $lowStockProducts }}</strong> محصولات کم موجودي لري!</span>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-sm">
            وروستي امرونه
        </div>
        @forelse ($recentOrders as $order)
            <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700">
                <div>
                    <div class="text-sm font-medium">{{ $order->customer->name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $order->created_at->format('Y/m/d') }}</div>
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
            </a>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه امر نشته
            </div>
        @endforelse
    </div>

    <div class="grid grid-cols-2 gap-3 mt-6">
        <a href="{{ route('orders.create') }}" class="flex items-center justify-center gap-2 bg-[#f53003] text-white rounded-xl py-4 font-medium">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            نوی امر
        </a>
        <a href="{{ route('products.create') }}" class="flex items-center justify-center gap-2 bg-blue-600 text-white rounded-xl py-4 font-medium">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            نوی محصول
        </a>
    </div>
@endsection