@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 flex items-center justify-center flex-shrink-0">
                <x-icon name="clipboard-document-list" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.orders') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $orders->total() }} {{ __('messages.orders') }}</p>
            </div>
        </div>
        <a href="{{ route('orders.create') }}" class="btn-primary btn-sm flex-shrink-0">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_order') }}
        </a>
    </div>

    {{-- All-time summary --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-brand/[0.08] dark:bg-brand/[0.12]"></div>
        <div class="relative">
            <div class="flex items-center gap-1.5">
                <x-icon name="clock" class="w-3.5 h-3.5 text-ink-400 dark:text-ink-500" strokeWidth="1.8"/>
                <span class="metric-label">{{ __('messages.all_time') }}</span>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-3">
                <div>
                    <p class="text-[10px] font-bold text-ink-400 dark:text-ink-500 mb-0.5">{{ __('messages.orders') }}</p>
                    <p class="text-lg font-extrabold tracking-tight text-ink-900 dark:text-white">
                        <span class="whitespace-nowrap"><bdi class="tabular-nums">{{ number_format($orderAllAFN) }}</bdi> <span class="text-[10px] font-bold text-ink-400 dark:text-ink-500">{{ __('messages.afn') }}</span></span>
                        @if ($orderAllUSD > 0) <span class="text-ink-300 dark:text-ink-600">&middot;</span> <bdi class="tabular-nums whitespace-nowrap">{{ number_format($orderAllUSD) }}$</bdi> @endif
                    </p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-ink-400 dark:text-ink-500 mb-0.5">{{ __('messages.purchases') }}</p>
                    <p class="text-lg font-extrabold tracking-tight text-ink-900 dark:text-white">
                        <span class="whitespace-nowrap"><bdi class="tabular-nums">{{ number_format($purchaseAllAFN) }}</bdi> <span class="text-[10px] font-bold text-ink-400 dark:text-ink-500">{{ __('messages.afn') }}</span></span>
                        @if ($purchaseAllUSD > 0) <span class="text-ink-300 dark:text-ink-600">&middot;</span> <bdi class="tabular-nums whitespace-nowrap">{{ number_format($purchaseAllUSD) }}$</bdi> @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Search (server-side) --}}
    <form method="GET" action="{{ route('orders.index') }}" class="search-bar sticky top-[3.5rem] z-10 mb-3 page-enter" style="animation-delay: 0.1s;">
        <x-icon name="magnifying-glass" class="search-icon" strokeWidth="1.8"/>
        <input type="search" inputmode="search" name="search" value="{{ $search }}"
               autocomplete="off"
               placeholder="{{ __('messages.search') }}..."
               class="flex-1">
    </form>

    {{-- Orders list --}}
    <div class="card overflow-hidden mb-3 page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-brand"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.orders') }}</h3>
            </div>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($orders as $order)
                <a href="{{ route('orders.show', $order) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-brand/10 dark:bg-brand/20 text-brand flex items-center justify-center flex-shrink-0">
                            <span class="font-bold text-xs tabular-nums">#{{ $order->id }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $order->party?->name ?? __('messages.unknown') }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400"><bdi>{{ local_date($order->created_at) }}</bdi></div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-extrabold tabular-nums text-ink-900 dark:text-ink-100">
                            <x-money :amount="$order->total_amount" :currency="$order->currency" symbol-class="text-[10px] font-medium text-ink-500"/>
                        </div>
                        @if ($order->status !== 'cancelled' && $order->remaining > 0)
                            <div class="text-[10px] text-accent-600 dark:text-accent-400 font-semibold mt-0.5">{{ __('messages.remaining') }}: <bdi>{{ number_format($order->remaining) }}</bdi></div>
                        @endif
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
                <x-empty-state title="{{ __('messages.no_orders') }}" :description="__('messages.create_first_order')">
                    <x-icon name="clipboard-document-list" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
    </div>
    @if ($orders->hasPages())
        <div class="mb-4">{{ $orders->links() }}</div>
    @endif

    {{-- Quick purchase entry --}}
    <div class="flex items-center justify-between mb-3 mt-1 page-enter" style="animation-delay: 0.18s;">
        <div class="flex items-center gap-2 min-w-0">
            <div class="w-7 h-7 rounded-lg bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="shopping-bag" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <h3 class="text-sm font-bold text-ink-800 dark:text-ink-200 truncate">{{ __('messages.purchases') }}</h3>
        </div>
        <a href="{{ route('purchases.create') }}" class="btn-primary btn-sm flex-shrink-0">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_purchase') }}
        </a>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.2s;">
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($purchases as $purchase)
                <a href="{{ route('purchases.show', $purchase) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center flex-shrink-0">
                            <span class="font-bold text-xs tabular-nums text-accent-600 dark:text-accent-400">#{{ $purchase->id }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400"><bdi>{{ local_date($purchase->created_at) }}</bdi></div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-extrabold tabular-nums text-ink-900 dark:text-ink-100">
                            <x-money :amount="$purchase->total_amount" :currency="$purchase->currency" symbol-class="text-[10px] font-medium text-ink-500"/>
                        </div>
                        @if ($purchase->status !== 'cancelled' && $purchase->remaining > 0)
                            <div class="text-[10px] text-accent-600 dark:text-accent-400 font-semibold mt-0.5">{{ __('messages.remaining') }}: <bdi>{{ number_format($purchase->remaining) }}</bdi></div>
                        @endif
                        <span class="inline-flex items-center gap-1 badge mt-0.5
                            @if($purchase->list_status === 'completed') badge-success
                            @elseif($purchase->list_status === 'processing') badge-info
                            @elseif($purchase->list_status === 'cancelled') badge-danger
                            @elseif($purchase->list_status === 'paid') badge-success
                            @elseif($purchase->list_status === 'partial') badge-info
                            @else badge-warning @endif">
                            @switch($purchase->list_status)
                                @case('completed') {{ __('messages.completed') }} @break
                                @case('processing') {{ __('messages.processing') }} @break
                                @case('cancelled') {{ __('messages.cancelled') }} @break
                                @case('paid') {{ __('messages.paid') }} @break
                                @case('partial') {{ __('messages.partially_paid') }} @break
                                @default {{ __('messages.pending') }}
                            @endswitch
                        </span>
                    </div>
                </a>
            @empty
                <x-empty-state title="{{ __('messages.no_purchases') }}" :description="__('messages.create_first_purchase')">
                    <x-icon name="shopping-bag" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse
        </div>
    </div>
    @if ($purchases->hasPages())
        <div class="mt-4">{{ $purchases->links() }}</div>
    @endif
@endsection
