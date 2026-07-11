@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('orders.returns.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center shadow-sm">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.order_return') }} #{{ $orderReturn->id }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.order') }} #{{ $orderReturn->order_id }}</p>
                </div>
            </div>
            <span class="badge @if($orderReturn->status === 'completed') badge-success @elseif($orderReturn->status === 'processing') badge-info @elseif($orderReturn->status === 'cancelled') badge-danger @else badge-warning @endif">
                <span class="status-dot @if($orderReturn->status === 'completed') bg-primary-500 @elseif($orderReturn->status === 'processing') bg-secondary-500 @elseif($orderReturn->status === 'cancelled') bg-red-500 @else bg-amber-500 @endif"></span>
                @switch($orderReturn->status) @case('completed') {{ __('messages.completed') }} @break @case('processing') {{ __('messages.processing') }} @break @case('cancelled') {{ __('messages.cancelled') }} @break @default {{ __('messages.pending') }} @endswitch
            </span>
        </div>
        <div class="space-y-1.5 text-sm">
            <div><span class="text-gray-500 dark:text-gray-400">{{ __('messages.customer') }}: </span><span class="font-medium text-gray-800 dark:text-gray-200">{{ $orderReturn->customer?->name }}</span></div>
            <div><span class="text-gray-500 dark:text-gray-400">{{ __('messages.return_date') }}: </span><span class="font-medium">{{ $orderReturn->return_date->format('Y/m/d') }}</span></div>
            @if ($orderReturn->reason)<div><span class="text-gray-500 dark:text-gray-400">{{ __('messages.reason') }}: </span><span class="font-medium">{{ $orderReturn->reason }}</span></div>@endif
            <div><span class="text-gray-500 dark:text-gray-400">{{ __('messages.currency_unit') }}: </span><span class="font-medium">{{ $orderReturn->order->currency === 'USD' ? __('messages.usd_with_paren') . '$)' : __('messages.afn') . ' (' . __('messages.afn') . ')' }}</span></div>
        </div>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('messages.return_items') }}</h3>
            </div>
        </div>
        @foreach ($orderReturn->items as $item)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 last:border-b-0">
                <div>
                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $item->product->name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->quantity }}@if($item->product->unit) {{ $item->product->unit->short_name ?? $item->product->unit->name }}@endif x {{ number_format($item->unit_price) }} {{ $orderReturn->order->currency === 'USD' ? '$' : __('messages.afn') }}</div>
                </div>
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format($item->subtotal) }} {{ $orderReturn->order->currency === 'USD' ? '$' : __('messages.afn') }}</div>
            </div>
        @endforeach
        <div class="flex items-center justify-between px-4 py-3 bg-primary-50 dark:bg-primary-900/10 font-bold">
            <span class="text-sm text-gray-900 dark:text-white">{{ __('messages.total') }}</span>
            <span class="text-sm text-primary-700 dark:text-primary-300">{{ number_format($orderReturn->total_amount) }} {{ $orderReturn->order->currency === 'USD' ? '$' : __('messages.afn') }}</span>
        </div>
    </div>

    @if ($orderReturn->status !== 'cancelled')
        <form action="{{ route('orders.returns.destroy', $orderReturn) }}" method="POST" class="page-enter" style="animation-delay: 0.15s;" onsubmit="return confirm('{{ __('messages.confirm_delete_return') }}')">
            @csrf @method('DELETE')
            <button class="btn-danger w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>{{ __('messages.delete_return') }}</button>
        </form>
    @endif
@endsection