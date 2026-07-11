@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.purchase_return') }}</h2>
        <a href="{{ route('purchases.returns.create') }}" class="btn-primary btn-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('messages.new_return') }}
        </a>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        @forelse ($purchaseReturns as $purchaseReturn)
            <a href="{{ route('purchases.returns.show', $purchaseReturn) }}" class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ __('messages.purchase') }} #{{ $purchaseReturn->purchase_id }} - {{ $purchaseReturn->purchase->party?->name ?? __('messages.unknown') }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $purchaseReturn->return_date->format('d M Y') }}</div>
                    </div>
                </div>
                <div class="text-right flex-shrink-0 ml-3">
                    <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ number_format($purchaseReturn->total_amount) }} {{ $purchaseReturn->purchase->currency === 'USD' ? '$' : __('messages.afn') }}</div>
                    <span class="inline-flex items-center gap-1 badge mt-0.5
                        @if($purchaseReturn->status === 'completed') badge-success
                        @elseif($purchaseReturn->status === 'processing') badge-info
                        @elseif($purchaseReturn->status === 'cancelled') badge-danger
                        @else badge-warning @endif">
                        @switch($purchaseReturn->status)
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
                <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('messages.no_purchase_returns') }}</p>
            </div>
        @endforelse
    </div>
@endsection