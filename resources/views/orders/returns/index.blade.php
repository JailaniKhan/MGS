@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.order_return') }}</h2>
        <a href="{{ route('orders.returns.create') }}" class="btn-primary btn-sm">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_return') }}
        </a>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        @forelse ($orderReturns as $orderReturn)
            <a href="{{ route('orders.returns.show', $orderReturn) }}" class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-danger-500 to-danger-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <x-icon name="arrow-uturn-left" class="w-4 h-4 text-white"/>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ __('messages.order') }} #{{ $orderReturn->order_id }} - {{ $orderReturn->order->party?->name }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $orderReturn->return_date->format('d M Y') }}</div>
                    </div>
                </div>
                <div class="text-right flex-shrink-0 ml-3">
                    <div class="text-sm font-bold text-ink-900 dark:text-ink-100">{{ number_format($orderReturn->total_amount) }} {{ $orderReturn->order->currency === 'USD' ? '$' : __('messages.afn') }}</div>
                    <span class="inline-flex items-center gap-1 badge mt-0.5
                        @if($orderReturn->status === 'completed') badge-success
                        @elseif($orderReturn->status === 'processing') badge-info
                        @elseif($orderReturn->status === 'cancelled') badge-danger
                        @else badge-warning @endif">
                        @switch($orderReturn->status)
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
                    <x-icon name="arrow-uturn-left" class="w-6 h-6 text-ink-400"/>
                </div>
                <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_order_returns') }}</p>
            </div>
        @endforelse
    </div>
    @if ($orderReturns->hasPages())
        <div class="mt-4">{{ $orderReturns->links() }}</div>
    @endif
@endsection