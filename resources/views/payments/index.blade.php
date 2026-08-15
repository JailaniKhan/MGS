@extends('layouts.app')

@section('content')
    <div class="page-header page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.wallet') }}</h2>
        <a href="{{ route('payments.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.new_payment') }}</a>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="p-4 bg-brand text-white">
            <div class="text-xs text-primary-100 mb-1">{{ __('messages.total_balance') }}</div>
            <div class="text-lg font-bold">{{ number_format($balanceAFN, 2) }} {{ __('messages.afn') }}</div>
            <div class="text-base font-semibold text-primary-200 mt-0.5">{{ number_format($balanceUSD, 2) }} $</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="card p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                    <x-icon name="plus" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="2"/>
                </div>
                <span class="text-xs font-semibold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.incoming_payments') }}</span>
            </div>
            <div class="text-base font-bold text-primary-600 dark:text-primary-300">{{ number_format($incomingAFN, 2) }} {{ __('messages.afn') }}</div>
            <div class="text-sm font-semibold text-primary-500 dark:text-primary-400">{{ number_format($incomingUSD, 2) }} $</div>
        </div>
        <div class="card p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-danger-100 dark:bg-danger-900/30 flex items-center justify-center">
                    <x-icon name="minus" class="w-4 h-4 text-danger-600 dark:text-danger-400" strokeWidth="2"/>
                </div>
                <span class="text-xs font-semibold text-ink-500 dark:text-ink-400 uppercase tracking-wider">{{ __('messages.outgoing_payments') }}</span>
            </div>
            <div class="text-base font-bold text-danger-600 dark:text-danger-300">{{ number_format($outgoingAFN, 2) }} {{ __('messages.afn') }}</div>
            <div class="text-sm font-semibold text-danger-500 dark:text-danger-400">{{ number_format($outgoingUSD, 2) }} $</div>
        </div>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.12s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.outstanding_balances') }}</h3>
            </div>
        </div>

        <div class="px-4 py-2 bg-primary-50 dark:bg-primary-900/10 text-xs font-semibold text-primary-700 dark:text-primary-300">{{ __('messages.receivables') }} &middot; {{ __('messages.afn') }}</div>
        @forelse ($receivablesAFN as $order)
            <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-ink-800 dark:text-ink-200 truncate">{{ $order->party?->name ?? __('messages.unknown') }} — {{ __('messages.order') }} #{{ $order->id }}</div>
                    <div class="text-xs text-ink-500 dark:text-ink-400">{{ __('messages.'.$order->list_status) }}</div>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ms-3 text-primary-600 dark:text-primary-400">{{ number_format($order->remaining, 2) }} {{ __('messages.afn') }}</span>
            </a>
        @empty
            <div class="px-4 py-3 text-xs text-ink-500 dark:text-ink-400">{{ __('messages.no_outstanding') }}</div>
        @endforelse
        @if ($receivablesAFN->hasPages())
            <div class="px-4 py-2">{{ $receivablesAFN->links() }}</div>
        @endif

        <div class="px-4 py-2 bg-primary-50 dark:bg-primary-900/10 text-xs font-semibold text-primary-700 dark:text-primary-300">{{ __('messages.receivables') }} &middot; {{ __('messages.usd') }}</div>
        @forelse ($receivablesUSD as $order)
            <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-ink-800 dark:text-ink-200 truncate">{{ $order->party?->name ?? __('messages.unknown') }} — {{ __('messages.order') }} #{{ $order->id }}</div>
                    <div class="text-xs text-ink-500 dark:text-ink-400">{{ __('messages.'.$order->list_status) }}</div>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ms-3 text-primary-600 dark:text-primary-400">{{ number_format($order->remaining, 2) }} $</span>
            </a>
        @empty
            <div class="px-4 py-3 text-xs text-ink-500 dark:text-ink-400">{{ __('messages.no_outstanding') }}</div>
        @endforelse
        @if ($receivablesUSD->hasPages())
            <div class="px-4 py-2">{{ $receivablesUSD->links() }}</div>
        @endif

        <div class="px-4 py-2 bg-danger-50 dark:bg-danger-900/10 text-xs font-semibold text-danger-700 dark:text-danger-300 border-t border-ink-100 dark:border-ink-700/30">{{ __('messages.payables') }} &middot; {{ __('messages.afn') }}</div>
        @forelse ($payablesAFN as $purchase)
            <a href="{{ route('purchases.show', $purchase) }}" class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-ink-800 dark:text-ink-200 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }} — {{ __('messages.purchase') }} #{{ $purchase->id }}</div>
                    <div class="text-xs text-ink-500 dark:text-ink-400">{{ __('messages.'.$purchase->list_status) }}</div>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ms-3 text-danger-600 dark:text-danger-400">{{ number_format($purchase->remaining, 2) }} {{ __('messages.afn') }}</span>
            </a>
        @empty
            <div class="px-4 py-3 text-xs text-ink-500 dark:text-ink-400">{{ __('messages.no_outstanding') }}</div>
        @endforelse
        @if ($payablesAFN->hasPages())
            <div class="px-4 py-2">{{ $payablesAFN->links() }}</div>
        @endif

        <div class="px-4 py-2 bg-danger-50 dark:bg-danger-900/10 text-xs font-semibold text-danger-700 dark:text-danger-300 border-t border-ink-100 dark:border-ink-700/30">{{ __('messages.payables') }} &middot; {{ __('messages.usd') }}</div>
        @forelse ($payablesUSD as $purchase)
            <a href="{{ route('purchases.show', $purchase) }}" class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-ink-800 dark:text-ink-200 truncate">{{ $purchase->party?->name ?? __('messages.unknown') }} — {{ __('messages.purchase') }} #{{ $purchase->id }}</div>
                    <div class="text-xs text-ink-500 dark:text-ink-400">{{ __('messages.'.$purchase->list_status) }}</div>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ms-3 text-danger-600 dark:text-danger-400">{{ number_format($purchase->remaining, 2) }} $</span>
            </a>
        @empty
            <div class="px-4 py-3 text-xs text-ink-500 dark:text-ink-400">{{ __('messages.no_outstanding') }}</div>
        @endforelse
        @if ($payablesUSD->hasPages())
            <div class="px-4 py-2">{{ $payablesUSD->links() }}</div>
        @endif
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.recent_transactions') }}</h3>
            </div>
        </div>
        <div id="pay-tx-tabs" class="flex gap-2 px-4 py-2.5 border-b border-ink-100 dark:border-ink-700/30">
            <button type="button" data-filter="all" class="tab-btn px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-200 cursor-pointer bg-primary-600 text-white shadow-sm shadow-primary-600/30">{{ __('messages.all') }}</button>
            <button type="button" data-filter="AFN" class="tab-btn px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-200 cursor-pointer bg-ink-100 dark:bg-ink-800 text-ink-500 dark:text-ink-400">{{ __('messages.afn') }}</button>
            <button type="button" data-filter="USD" class="tab-btn px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-200 cursor-pointer bg-ink-100 dark:bg-ink-800 text-ink-500 dark:text-ink-400">{{ __('messages.usd') }}</button>
        </div>
        <div id="pay-tx-list">
        @forelse ($transactions as $tx)
            <a href="{{ $tx['link'] ?? '#' }}" class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0 hover:bg-ink-50 dark:hover:bg-ink-800/40 transition-colors" data-currency="{{ $tx['currency'] }}">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 {{ $tx['type'] === 'incoming' ? 'bg-primary-100 dark:bg-primary-900/30' : 'bg-danger-100 dark:bg-danger-900/30' }}">
                        @if ($tx['type'] === 'incoming')
                            <x-icon name="plus" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="2"/>
                        @else
                            <x-icon name="minus" class="w-4 h-4 text-danger-600 dark:text-danger-400" strokeWidth="2"/>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-ink-800 dark:text-ink-200 truncate">{{ $tx['description'] }}</div>
                        <div class="text-xs text-ink-500 dark:text-ink-400">{{ $tx['date']->format('Y/m/d H:i') }} @if ($tx['notes']) | {{ $tx['notes'] }} @endif</div>
                    </div>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ms-3 {{ $tx['type'] === 'incoming' ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">{{ $tx['type'] === 'incoming' ? '+' : '-' }}{{ number_format($tx['amount'], 2) }} {{ $tx['currency'] === 'USD' ? '$' : __('messages.afn') }}</span>
            </a>
        @empty
            <div class="empty-state"><p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_transactions') }}</p></div>
        @endforelse
        </div>
        <div id="pay-tx-empty" class="empty-state" style="display:none;"><p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_transactions') }}</p></div>
    </div>
    @if ($transactions->hasPages())
        <div class="mt-4">{{ $transactions->links() }}</div>
    @endif
@endsection

@push('scripts')
    @once
        <script>
            (function () {
                const tabs = document.querySelectorAll('#pay-tx-tabs [data-filter]');
                if (!tabs.length) return;
                const ACTIVE = ['bg-primary-600', 'text-white', 'shadow-sm', 'shadow-primary-600/30'];
                const INACTIVE = ['bg-ink-100', 'dark:bg-ink-800', 'text-ink-500', 'dark:text-ink-400'];
                tabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        const filter = tab.dataset.filter;
                        tabs.forEach(function (t) {
                            if (t === tab) {
                                ACTIVE.forEach(function (c) { t.classList.add(c); });
                                INACTIVE.forEach(function (c) { t.classList.remove(c); });
                            } else {
                                INACTIVE.forEach(function (c) { t.classList.add(c); });
                                ACTIVE.forEach(function (c) { t.classList.remove(c); });
                            }
                        });
                        document.querySelectorAll('#pay-tx-list [data-currency]').forEach(function (row) {
                            row.style.display = (filter === 'all' || row.dataset.currency === filter) ? '' : 'none';
                        });
                        const rows = document.querySelectorAll('#pay-tx-list [data-currency]');
                        const visible = Array.prototype.filter.call(rows, function (row) {
                            return row.style.display !== 'none';
                        }).length;
                        const empty = document.getElementById('pay-tx-empty');
                        if (empty) { empty.style.display = visible === 0 ? '' : 'none'; }
                    });
                });
            })();
        </script>
    @endonce
@endpush