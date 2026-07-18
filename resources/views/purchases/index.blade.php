@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.purchases') }}</h2>
        <a href="{{ route('purchases.create') }}" class="btn-primary btn-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            {{ __('messages.new_purchase') }}
        </a>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
            @forelse ($purchases as $purchase)
                <a href="{{ route('purchases.show', $purchase) }}" class="list-row">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }}</div>
                            <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $purchase->created_at->format('d M, H:i') }}</div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100">
                            {{ number_format($purchase->total_amount) }}
                            <span class="text-[10px] font-normal text-ink-500">{{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</span>
                        </div>
                        <span class="inline-flex items-center gap-1 badge mt-0.5
                            @if($purchase->display_status === 'completed') badge-success
                            @elseif($purchase->display_status === 'processing') badge-info
                            @elseif($purchase->display_status === 'cancelled') badge-danger
                            @elseif($purchase->display_status === 'paid') badge-success
                            @else badge-warning @endif">
                            @switch($purchase->display_status)
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
                    <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_purchases') }}</p>
                    <p class="text-xs text-ink-400 dark:text-ink-500 mt-1">{{ __('messages.create_first_purchase') }}</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection