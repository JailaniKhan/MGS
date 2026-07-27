@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.transactions') }}</h2>
    </div>

    <div class="flex gap-1.5 mb-4 page-enter" style="animation-delay: 0.05s;">
        <button id="tab-orders" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-primary-500 text-white shadow-sm shadow-primary-500/20" onclick="switchTab('orders')">
            {{ __('messages.orders') }}
        </button>
        <button id="tab-purchases" class="tab-btn px-4 py-2 text-xs font-bold rounded-xl bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-300 border border-ink-200 dark:border-ink-700" onclick="switchTab('purchases')">
            {{ __('messages.purchases') }}
        </button>
    </div>

    <div id="section-orders" class="tab-section page-enter" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.orders') }}</span>
            <a href="{{ route('orders.create') }}" class="btn-primary btn-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
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
                    <div class="text-right flex-shrink-0 ml-3">
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
                    <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
                        </svg>
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
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('messages.new_purchase') }}
            </a>
        </div>
        <div class="card overflow-hidden">
            @forelse ($purchases as $purchase)
                <a href="{{ route('purchases.show', $purchase) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                            <span class="text-white font-bold text-xs">{{ $purchase->id }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $purchase->created_at->format('d M, H:i') }}</div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
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
                    <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                        </svg>
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
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('bg-primary-500', 'text-white', 'shadow-sm', 'shadow-primary-500/20');
            el.classList.add('bg-ink-100', 'dark:bg-ink-800', 'text-ink-600', 'dark:text-ink-300', 'border', 'border-ink-200', 'dark:border-ink-700');
        });
        const activeBtn = document.getElementById('tab-' + tab);
        activeBtn.classList.remove('bg-ink-100', 'dark:bg-ink-800', 'text-ink-600', 'dark:text-ink-300', 'border', 'border-ink-200', 'dark:border-ink-700');
        activeBtn.classList.add('bg-primary-500', 'text-white', 'shadow-sm', 'shadow-primary-500/20');
    }

    @if (request()->has('purchases_page'))
        switchTab('purchases');
    @endif
</script>
@endpush