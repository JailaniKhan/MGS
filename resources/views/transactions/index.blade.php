@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.transactions') }}</h2>
    </div>

    <div class="segmented mb-4 page-enter" style="animation-delay: 0.05s;">
        <button id="tab-orders" class="tab-btn segmented-item segmented-item-active" onclick="switchTab('orders')">
            {{ __('messages.orders') }}
        </button>
        <button id="tab-purchases" class="tab-btn segmented-item" onclick="switchTab('purchases')">
            {{ __('messages.purchases') }}
        </button>
    </div>

    <div id="section-orders" class="tab-section page-enter" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.orders') }}</span>
            <a href="{{ route('orders.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
                {{ __('messages.new_order') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            @forelse ($orders as $order)
                <a href="{{ route('orders.show', $order) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded-xl bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                            <span class="text-white font-bold text-xs">{{ $order->id }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $order->party?->name }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $order->created_at->format('d M, H:i') }}</div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100">{{ number_format($order->total_amount) }} {{ $order->currency === 'USD' ? '$' : __('messages.afn') }}</div>
                        <span class="inline-flex items-center gap-1 badge mt-0.5
                            @if($order->display_status === 'paid' || $order->display_status === 'completed') badge-success
                            @elseif($order->display_status === 'processing') badge-info
                            @elseif($order->display_status === 'cancelled') badge-danger
                            @else badge-warning @endif">
                            @switch($order->display_status)
                                @case('paid') {{ __('messages.paid') }} @break
                                @case('completed') {{ __('messages.completed') }} @break
                                @case('processing') {{ __('messages.processing') }} @break
                                @case('cancelled') {{ __('messages.cancelled') }} @break
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
                </div>
            @endforelse
        </div>
        @if ($orders->hasPages())
            <div class="mt-3">{{ $orders->links() }}</div>
        @endif
    </div>

    <div id="section-purchases" class="tab-section hidden page-enter">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.purchases') }}</span>
            <a href="{{ route('purchases.create') }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
                {{ __('messages.new_purchase') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            @forelse ($purchases as $purchase)
                <a href="{{ route('purchases.show', $purchase) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded-[0.875rem] bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center flex-shrink-0 border border-accent-100 dark:border-accent-800/40">
                            <span class="text-white font-bold text-xs">{{ $purchase->id }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $purchase->created_at->format('d M, H:i') }}</div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-3">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100">{{ number_format($purchase->total_amount) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</div>
                        <span class="inline-flex items-center gap-1 badge mt-0.5
                            @if($purchase->display_status === 'paid' || $purchase->display_status === 'completed') badge-success
                            @elseif($purchase->display_status === 'processing') badge-info
                            @elseif($purchase->display_status === 'cancelled') badge-danger
                            @else badge-warning @endif">
                            @switch($purchase->display_status)
                                @case('paid') {{ __('messages.paid') }} @break
                                @case('completed') {{ __('messages.completed') }} @break
                                @case('processing') {{ __('messages.processing') }} @break
                                @case('cancelled') {{ __('messages.cancelled') }} @break
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
                </div>
            @endforelse
        </div>
        @if ($purchases->hasPages())
            <div class="mt-3">{{ $purchases->links() }}</div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-section').forEach(el => el.classList.add('hidden'));
        document.getElementById('section-' + tab).classList.remove('hidden');
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('segmented-item-active'));
        document.getElementById('tab-' + tab).classList.add('segmented-item-active');
    }

    @if (request()->has('purchases_page'))
        switchTab('purchases');
    @endif
</script>
@endpush