@extends('layouts.app')

@section('content')
    <div class="page-header page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.wallet') }}</h2>
        <a href="{{ route('payments.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.new_payment') }}</a>
    </div>

    {{-- Wallet hero: net balance across both currencies --}}
    <div class="hero-aurora rounded-[1.5rem] p-5 mb-4 text-white relative overflow-hidden page-enter" style="animation-delay: 0.05s;">
        <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="absolute -right-2 top-10 w-16 h-16 rounded-full bg-white/5"></div>
        <p class="relative text-[11px] font-semibold uppercase tracking-[0.14em] text-white/80">{{ __('messages.total_balance') }}</p>
        <div class="relative flex items-end gap-6 mt-2">
            <div>
                <p class="text-3xl font-extrabold tabular-nums tracking-tight text-glow-soft" data-count="{{ $balanceAFN }}">{{ number_format($balanceAFN) }}</p>
                <p class="text-[11px] font-semibold text-white/70 mt-0.5">{{ __('messages.afn') }}</p>
            </div>
            <div>
                <p class="text-3xl font-extrabold tabular-nums tracking-tight text-glow-soft" data-count="{{ $balanceUSD }}">{{ number_format($balanceUSD) }}</p>
                <p class="text-[11px] font-semibold text-white/70 mt-0.5">{{ __('messages.usd') }}</p>
            </div>
        </div>
        <div class="relative mt-4 pt-3 border-t border-white/15 grid grid-cols-2 gap-3 text-[11px] font-medium text-white/90">
            <div>
                <span class="flex items-center gap-1.5 text-white/70"><x-icon name="arrow-down-tray" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.incoming_payments') }}</span>
                <p class="text-sm font-bold mt-1 tabular-nums">{{ number_format($incomingAFN, 2) }} {{ __('messages.afn') }} <span class="font-semibold text-white/60">&middot; {{ number_format($incomingUSD, 2) }} $</span></p>
            </div>
            <div>
                <span class="flex items-center gap-1.5 text-white/70"><x-icon name="arrow-up-tray" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.outgoing_payments') }}</span>
                <p class="text-sm font-bold mt-1 tabular-nums">{{ number_format($outgoingAFN, 2) }} {{ __('messages.afn') }} <span class="font-semibold text-white/60">&middot; {{ number_format($outgoingUSD, 2) }} $</span></p>
            </div>
        </div>
    </div>

    {{-- Outstanding balance summary: receivables vs payables --}}
    <div class="grid grid-cols-2 gap-3 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.receivables') }}</span>
            <div class="mt-1.5 space-y-2">
                <div>
                    <p class="text-lg font-extrabold text-primary-600 dark:text-primary-300 tabular-nums" data-count="{{ $receivablesTotalAFN }}">{{ number_format((float) $receivablesTotalAFN, 0) }}</p>
                    <p class="text-[9px] text-ink-400">{{ __('messages.afn') }}</p>
                </div>
                <div class="pt-2 border-t border-ink-100 dark:border-white/[0.06]">
                    <p class="text-base font-bold text-primary-600 dark:text-primary-300 tabular-nums" data-count="{{ $receivablesTotalUSD }}">{{ number_format((float) $receivablesTotalUSD, 0) }} $</p>
                    <p class="text-[9px] text-ink-400">{{ __('messages.usd') }}</p>
                </div>
            </div>
        </div>
        <div class="metric-tile">
            <span class="metric-label">{{ __('messages.payables') }}</span>
            <div class="mt-1.5 space-y-2">
                <div>
                    <p class="text-lg font-extrabold text-danger-500 tabular-nums" data-count="{{ $payablesTotalAFN }}">{{ number_format((float) $payablesTotalAFN, 0) }}</p>
                    <p class="text-[9px] text-ink-400">{{ __('messages.afn') }}</p>
                </div>
                <div class="pt-2 border-t border-ink-100 dark:border-white/[0.06]">
                    <p class="text-base font-bold text-danger-500 tabular-nums" data-count="{{ $payablesTotalUSD }}">{{ number_format((float) $payablesTotalUSD, 0) }} $</p>
                    <p class="text-[9px] text-ink-400">{{ __('messages.usd') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Outstanding balances detail --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-primary-500"></div>
            <span class="section-header-title">{{ __('messages.outstanding_balances') }}</span>
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

    {{-- Recent transactions --}}
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.18s;">
        <div class="section-header">
            <div class="w-1 h-4 rounded-full bg-brand"></div>
            <span class="section-header-title">{{ __('messages.recent_transactions') }}</span>
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
                            <x-icon name="arrow-down-tray" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="2"/>
                        @else
                            <x-icon name="arrow-up-tray" class="w-4 h-4 text-danger-600 dark:text-danger-400" strokeWidth="2"/>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-ink-800 dark:text-ink-200 truncate">{{ $tx['description'] }}</div>
                        <div class="text-xs text-ink-500 dark:text-ink-400"><bdi>{{ local_date($tx['date'], 'Y/m/d H:i') }}</bdi> @if ($tx['notes']) | {{ $tx['notes'] }} @endif</div>
                    </div>
                </div>
                <span class="text-sm font-bold flex-shrink-0 ms-3 {{ $tx['type'] === 'incoming' ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}"><bdi>{{ $tx['type'] === 'incoming' ? '+' : '-' }}{{ number_format($tx['amount'], 2) }}</bdi> {{ $tx['currency'] === 'USD' ? '$' : __('messages.afn') }}</span>
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
