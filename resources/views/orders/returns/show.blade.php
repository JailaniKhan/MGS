@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('orders.returns.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[0.875rem] bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center border border-accent-100 dark:border-accent-800/40">
                    <x-icon name="arrow-uturn-left" class="w-4 h-4 text-white"/>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.order_return') }} #{{ $orderReturn->id }}</h2>
                    <p class="text-xs text-ink-500 dark:text-ink-400">{{ __('messages.order') }} #{{ $orderReturn->order_id }}</p>
                </div>
            </div>
            <span class="badge @if($orderReturn->status === 'completed') badge-success @elseif($orderReturn->status === 'processing') badge-info @elseif($orderReturn->status === 'cancelled') badge-danger @else badge-warning @endif">
                <span class="status-dot @if($orderReturn->status === 'completed') bg-primary-500 @elseif($orderReturn->status === 'processing') bg-secondary-500 @elseif($orderReturn->status === 'cancelled') bg-danger-500 @else bg-accent-500 @endif"></span>
                @switch($orderReturn->status) @case('completed') {{ __('messages.completed') }} @break @case('processing') {{ __('messages.processing') }} @break @case('cancelled') {{ __('messages.cancelled') }} @break @default {{ __('messages.pending') }} @endswitch
            </span>
        </div>
        <div class="space-y-1.5 text-sm">
            <div><span class="text-ink-500 dark:text-ink-400">{{ __('messages.customer') }}: </span><span class="font-medium text-ink-800 dark:text-ink-200">{{ $orderReturn->customer?->name }}</span></div>
            <div><span class="text-ink-500 dark:text-ink-400">{{ __('messages.return_date') }}: </span><span class="font-medium">{{ $orderReturn->return_date->format('Y/m/d') }}</span></div>
            @if ($orderReturn->reason)<div><span class="text-ink-500 dark:text-ink-400">{{ __('messages.reason') }}: </span><span class="font-medium">{{ $orderReturn->reason }}</span></div>@endif
            <div><span class="text-ink-500 dark:text-ink-400">{{ __('messages.currency_unit') }}: </span><span class="font-medium">{{ $orderReturn->order->currency === 'USD' ? __('messages.usd_with_paren') . '$)' : __('messages.afn') . ' (' . __('messages.afn') . ')' }}</span></div>
        </div>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.return_items') }}</h3>
            </div>
        </div>
        @foreach ($orderReturn->items as $item)
            <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                <div>
                    <div class="text-sm font-medium text-ink-800 dark:text-ink-200">{{ $item->product->name }}</div>
                    <div class="text-xs text-ink-500 dark:text-ink-400">{{ $item->quantity }}@if($item->product->unit) {{ $item->product->unit->short_name ?? $item->product->unit->name }}@endif x {{ number_format($item->unit_price) }} {{ $orderReturn->order->currency === 'USD' ? '$' : __('messages.afn') }}</div>
                </div>
                <div class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ number_format($item->subtotal) }} {{ $orderReturn->order->currency === 'USD' ? '$' : __('messages.afn') }}</div>
            </div>
        @endforeach
        <div class="flex items-center justify-between px-4 py-3 bg-primary-50 dark:bg-primary-900/10 font-bold">
            <span class="text-sm text-ink-900 dark:text-white">{{ __('messages.total') }}</span>
            <span class="text-sm text-primary-700 dark:text-primary-300">{{ number_format($orderReturn->total_amount) }} {{ $orderReturn->order->currency === 'USD' ? '$' : __('messages.afn') }}</span>
        </div>
    </div>

    @if ($orderReturn->status !== 'cancelled')
        <form action="{{ route('orders.returns.destroy', $orderReturn) }}" method="POST" class="page-enter" style="animation-delay: 0.15s;" onsubmit="return confirm('{{ __('messages.confirm_delete_return') }}')">
            @csrf @method('DELETE')
            <button class="btn-danger w-full"><x-icon name="trash" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.delete_return') }}</button>
        </form>
    @endif
@endsection