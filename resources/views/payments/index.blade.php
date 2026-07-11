@extends('layouts.app')

@section('content')
    <div class="page-header page-enter">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.wallet') }}</h2>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="p-4 bg-brand text-white text-white">
            <div class="text-xs text-primary-100 mb-1">{{ __('messages.total_balance') }}</div>
            <div class="text-lg font-bold">{{ number_format($balanceAFN, 2) }} {{ __('messages.afn') }}</div>
            <div class="text-base font-semibold text-primary-200 mt-0.5">{{ number_format($balanceUSD, 2) }} $</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="card p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                    <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('messages.incoming_payments') }}</span>
            </div>
            <div class="text-base font-bold text-primary-600 dark:text-primary-300">{{ number_format($incomingAFN, 2) }} {{ __('messages.afn') }}</div>
            <div class="text-sm font-semibold text-primary-500 dark:text-primary-400">{{ number_format($incomingUSD, 2) }} $</div>
        </div>
        <div class="card p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('messages.outgoing_payments') }}</span>
            </div>
            <div class="text-base font-bold text-red-600 dark:text-red-300">{{ number_format($outgoingAFN, 2) }} {{ __('messages.afn') }}</div>
            <div class="text-sm font-semibold text-red-500 dark:text-red-400">{{ number_format($outgoingUSD, 2) }} $</div>
        </div>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.12s;">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('messages.outstanding_balances') }}</h3>
            </div>
        </div>

        <div class="px-4 py-2 bg-primary-50 dark:bg-primary-900/10 text-xs font-semibold text-primary-700 dark:text-primary-300">{{ __('messages.receivables') }}</div>
        @forelse ($receivables as $order)
            <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 last:border-b-0">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $order->party?->name ?? __('messages.unknown') }} — {{ __('messages.order') }} #{{ $order->id }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.'.$order->display_status) }}</div>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ml-3 text-primary-600 dark:text-primary-400">{{ number_format($order->remaining_amount, 2) }} {{ $order->currency === 'USD' ? '$' : __('messages.afn') }}</span>
            </a>
        @empty
            <div class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.no_outstanding') }}</div>
        @endforelse

        <div class="px-4 py-2 bg-red-50 dark:bg-red-900/10 text-xs font-semibold text-red-700 dark:text-red-300 border-t border-gray-100 dark:border-gray-700/30">{{ __('messages.payables') }}</div>
        @forelse ($payables as $purchase)
            <a href="{{ route('purchases.show', $purchase) }}" class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 last:border-b-0">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }} — {{ __('messages.purchase') }} #{{ $purchase->id }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.'.$purchase->display_status) }}</div>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ml-3 text-red-600 dark:text-red-400">{{ number_format($purchase->remaining_amount, 2) }} {{ $purchase->currency === 'USD' ? '$' : __('messages.afn') }}</span>
            </a>
        @empty
            <div class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.no_outstanding') }}</div>
        @endforelse
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('messages.recent_transactions') }}</h3>
            </div>
        </div>
        @forelse ($transactions as $tx)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700/30 last:border-b-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 {{ $tx['type'] === 'incoming' ? 'bg-primary-100 dark:bg-primary-900/30' : 'bg-red-100 dark:bg-red-900/30' }}">
                        @if ($tx['type'] === 'incoming')
                            <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        @else
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $tx['description'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $tx['date']->format('Y/m/d H:i') }} @if ($tx['notes']) | {{ $tx['notes'] }} @endif</div>
                    </div>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ml-3 {{ $tx['type'] === 'incoming' ? 'text-primary-600 dark:text-primary-400' : 'text-red-600 dark:text-red-400' }}">{{ $tx['type'] === 'incoming' ? '+' : '-' }}{{ number_format($tx['amount'], 2) }} {{ $tx['currency'] === 'USD' ? '$' : __('messages.afn') }}</span>
            </div>
        @empty
            <div class="empty-state"><p class="text-sm text-gray-500 dark:text-gray-400">{{ __('messages.no_transactions') }}</p></div>
        @endforelse
    </div>
@endsection