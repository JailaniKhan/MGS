@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.orders') }}</h2>
        <div class="flex items-center gap-1.5">
            <a href="{{ route('orders.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
                {{ __('messages.new_order') }}
            </a>
            <a href="{{ route('purchases.create') }}" class="btn-secondary btn-sm">
                <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
                {{ __('messages.new_purchase') }}
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('orders.index') }}" class="mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="relative">
            <x-icon name="magnifying-glass" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 pointer-events-none"/>
            <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('messages.search') }}..."
                   class="form-input !pl-10">
        </div>
    </form>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-primary-500"></div>
            <span class="section-header-title">{{ __('messages.orders') }}</span>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($orders as $order)
                <a href="{{ route('orders.show', $order) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                            <span class="text-white font-bold text-xs">{{ $order->id }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $order->party?->name }}</div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[11px] text-ink-500 dark:text-ink-400">{{ $order->created_at->format('d M, H:i') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100">
                            {{ number_format($order->total_amount) }}
                            <span class="text-[10px] font-normal text-ink-500">{{ $order->currency === 'USD' ? '$' : __('messages.afn') }}</span>
                        </div>
                        <div class="mt-0.5">
                            @if ($order->status !== 'cancelled' && $order->remaining > 0)
                                <span class="text-[10px] text-accent-600 dark:text-accent-400 font-semibold">
                                    {{ __('messages.remaining') }}: {{ number_format($order->remaining) }}
                                </span>
                            @endif
                        </div>
                        <span class="inline-flex items-center gap-1 badge mt-0.5
                            @if($order->list_status === 'completed') badge-success
                            @elseif($order->list_status === 'processing') badge-info
                            @elseif($order->list_status === 'cancelled') badge-danger
                            @elseif($order->list_status === 'paid') badge-success
                            @else badge-warning @endif">
                            @switch($order->list_status)
                                @case('completed') {{ __('messages.completed') }} @break
                                @case('processing') {{ __('messages.processing') }} @break
                                @case('cancelled') {{ __('messages.cancelled') }} @break
                                @case('paid') {{ __('messages.paid') }} @break
                                @default {{ __('messages.pending') }}
                            @endswitch
                        </span>
                    </div>
                </a>
            @empty
                <div class="empty-state">
                    <div class="empty-illustration">
                        <x-icon name="clipboard-document-list" class="w-6 h-6 text-ink-400"/>
                    </div>
                    <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_orders') }}</p>
                    <p class="text-xs text-ink-400 dark:text-ink-500 mt-1">{{ __('messages.create_first_order') }}</p>
                </div>
            @endforelse
        </div>
    </div>
    @if ($orders->hasPages())
        <div class="mt-4 mb-4">{{ $orders->links() }}</div>
    @endif

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-accent-500"></div>
            <span class="section-header-title">{{ __('messages.purchases') }}</span>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($purchases as $purchase)
                <a href="{{ route('purchases.show', $purchase) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-[0.875rem] bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center flex-shrink-0 border border-accent-100 dark:border-accent-800/40">
                            <x-icon name="shopping-bag" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $purchase->created_at->format('d M, H:i') }}</div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100">
                            {{ number_format($purchase->total_amount) }}
                            <span class="text-[10px] font-normal text-ink-500">{{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</span>
                        </div>
                        <div class="mt-0.5">
                            @if ($purchase->status !== 'cancelled' && $purchase->remaining > 0)
                                <span class="text-[10px] text-accent-600 dark:text-accent-400 font-semibold">
                                    {{ __('messages.remaining') }}: {{ number_format($purchase->remaining) }}
                                </span>
                            @endif
                        </div>
                        <span class="inline-flex items-center gap-1 badge mt-0.5
                            @if($purchase->list_status === 'completed') badge-success
                            @elseif($purchase->list_status === 'processing') badge-info
                            @elseif($purchase->list_status === 'cancelled') badge-danger
                            @elseif($purchase->list_status === 'paid') badge-success
                            @else badge-warning @endif">
                            @switch($purchase->list_status)
                                @case('completed') {{ __('messages.completed') }} @break
                                @case('processing') {{ __('messages.processing') }} @break
                                @case('cancelled') {{ __('messages.cancelled') }} @break
                                @case('paid') {{ __('messages.paid') }} @break
                                @default {{ __('messages.pending') }}
                            @endswitch
                        </span>
                    </div>
                </a>
            @empty
                <div class="empty-state">
                    <div class="empty-illustration">
                        <x-icon name="shopping-bag" class="w-6 h-6 text-ink-400"/>
                    </div>
                    <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_purchases') }}</p>
                    <p class="text-xs text-ink-400 dark:text-ink-500 mt-1">{{ __('messages.create_first_purchase') }}</p>
                </div>
            @endforelse
        </div>
    </div>
    @if ($purchases->hasPages())
        <div class="mt-4">{{ $purchases->links() }}</div>
    @endif
@endsection
