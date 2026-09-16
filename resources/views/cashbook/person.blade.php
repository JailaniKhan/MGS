@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-primary-500/10 dark:bg-primary-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="wallet" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight truncate">{{ __('messages.cashbook') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $person->name }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('cashbook.index') }}"/>
    </div>

    {{-- Profile card --}}
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-[1.25rem] brand-grad flex items-center justify-center flex-shrink-0 shadow-fab">
                <span class="text-white font-extrabold text-2xl">{{ mb_substr($person->name, 0, 1) }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-bold text-ink-900 dark:text-white truncate">{{ $person->name }}</h2>
                <div class="mt-1.5">
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full capitalize {{ $personType === 'customer' ? 'bg-primary-500/10 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400' : 'bg-accent-500/10 text-accent-600 dark:bg-accent-400/10 dark:text-accent-400' }}">{{ $personType }}</span>
                </div>
                @if($person->phone)
                    <a href="tel:{{ $person->phone }}" class="mt-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-600 dark:text-ink-300 bg-ink-100 dark:bg-ink-800 rounded-full px-3 py-1.5">
                        <x-icon name="phone" class="w-3 h-3"/>{{ $person->phone }}
                    </a>
                @endif
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 mt-4">
            <form action="{{ route('cashbook.send-statement', [$personType, $person->id]) }}" method="POST" class="{{ $person->phone ? '' : 'col-span-2' }}">
                @csrf
                <button type="submit" class="btn-primary w-full">
                    <x-icon name="paper-airplane" class="w-4 h-4"/>{{ __('messages.send_statement') }}
                </button>
            </form>
            @if($person->phone)
                <a href="tel:{{ $person->phone }}" class="btn-secondary w-full">
                    <x-icon name="phone" class="w-4 h-4"/>{{ __('messages.call') }}
                </a>
            @endif
        </div>
    </div>

    {{-- Closing balance hero: the selected currency's final running balance --}}
    @php
        $closing = $closingBalances[$selectedCurrency] ?? '0.00';
        $closingPositive = bccomp((string) $closing, '0.00', 2) >= 0;
        $closingClass = $closingPositive ? 'text-primary-200' : 'text-danger-200';
        // Explicit sign: '+' when money is net owed out, '-' when net
        // received back, nothing for an exactly-zero balance.
        $closingSign = bccomp((string) $closing, '0.00', 2) > 0 ? '+' : ($closingPositive ? '' : '-');
    @endphp
    <div class="rounded-2xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 shadow-lg shadow-primary-600/25 p-5 mb-4 page-enter" style="animation-delay: 0.08s;">
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-[10px] font-bold tracking-wider text-white/60 uppercase">{{ __('messages.closing_balance') }}</span>
            <span class="text-[10px] font-extrabold bg-white/15 text-white px-2 py-0.5 rounded-full">{{ $selectedCurrency }}</span>
        </div>
        <div class="text-3xl font-extrabold tabular-nums {{ $closingClass }}" dir="ltr">
            {{ $closingSign }}{{ number_format(abs((float) $closing), 2) }}
        </div>
        <div class="grid grid-cols-2 gap-3 mt-4">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-white/15 flex items-center justify-center flex-shrink-0">
                    <x-icon name="arrow-down-circle" class="w-4 h-4 text-primary-200"/>
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] text-white/60 truncate">{{ __('messages.cashbook_in_total') }}</div>
                    <div class="text-sm font-bold text-white truncate" dir="ltr">{{ number_format($totals[$selectedCurrency]['in'] ?? 0, 2) }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-white/15 flex items-center justify-center flex-shrink-0">
                    <x-icon name="arrow-up-circle" class="w-4 h-4 text-danger-200"/>
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] text-white/60 truncate">{{ __('messages.cashbook_out_total') }}</div>
                    <div class="text-sm font-bold text-white truncate" dir="ltr">{{ number_format($totals[$selectedCurrency]['out'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Currency segmented control (only when both currencies have activity) --}}
    @if($availableCurrencies->count() > 1)
        <div class="segmented mb-4 page-enter" style="animation-delay: 0.1s;">
            @foreach(['AFN', 'USD'] as $cur)
                <a href="{{ route('cashbook.person', [$personType, $person->id, 'currency' => $cur]) }}"
                   class="segmented-item {{ $selectedCurrency === $cur ? 'segmented-item-active' : '' }} text-center"
                   aria-label="{{ $cur }}">
                    {{ $cur === 'USD' ? '$' : __('messages.afn') }}
                    <span class="text-[9px] font-semibold ms-1 opacity-70">{{ number_format(abs((float) ($closingBalances[$cur] ?? '0.00')), 0) }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Kind filter tabs --}}
    @if($transactions->isNotEmpty())
        <div id="tx-tabs" class="flex gap-2 mb-3 overflow-x-auto pb-0.5 page-enter" style="animation-delay: 0.1s;">
            <button type="button" data-filter="all" class="tab-btn px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-200 cursor-pointer bg-primary-600 text-white shadow-sm shadow-primary-600/30 whitespace-nowrap">{{ __('messages.all') }}</button>
            <button type="button" data-filter="cashbook" class="tab-btn px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-200 cursor-pointer bg-ink-100 dark:bg-ink-800 text-ink-500 dark:text-ink-400 whitespace-nowrap">{{ __('messages.cashbook') }}</button>
            <button type="button" data-filter="order" class="tab-btn px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-200 cursor-pointer bg-ink-100 dark:bg-ink-800 text-ink-500 dark:text-ink-400 whitespace-nowrap">{{ __('messages.orders') }}</button>
            <button type="button" data-filter="purchase" class="tab-btn px-3.5 py-1.5 rounded-full text-xs font-bold transition-all duration-200 cursor-pointer bg-ink-100 dark:bg-ink-800 text-ink-500 dark:text-ink-400 whitespace-nowrap">{{ __('messages.purchases') }}</button>
        </div>

        {{-- The statement list: every line shows its amount and the running
            balance — each line adds or subtracts from the previous line. --}}
        <div id="tx-list" class="card overflow-hidden page-enter" style="animation-delay: 0.15s;">
            @php
                $groups = [];
                foreach ($transactions as $tx) {
                    $key = $tx->date->isToday()
                        ? __('messages.today')
                        : ($tx->date->isYesterday() ? __('messages.yesterday') : local_date($tx->date, 'd M Y'));
                    $groups[$key][] = $tx;
                }
            @endphp
            @forelse ($groups as $groupLabel => $groupTx)
                <div class="tx-group">
                    <div class="flex items-center justify-between px-4 pt-3 pb-1.5">
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500">{{ $groupLabel }}</h4>
                        <span class="text-[10px] font-semibold text-ink-400">{{ count($groupTx) }}</span>
                    </div>
                    @foreach ($groupTx as $tx)
                        @php
                            $isLink = in_array($tx->kind, ['order', 'purchase'], true) && isset($tx->id);
                            $icon = $tx->kind === 'order' ? 'document-text' : ($tx->kind === 'purchase' ? 'shopping-bag' : ($tx->direction === 'in' ? 'plus' : 'minus'));
                            $bubble = $tx->direction === 'in' ? 'bg-gradient-to-br from-primary-500 to-primary-600' : 'bg-gradient-to-br from-danger-500 to-danger-600';
                            $balance = (string) $tx->balance;
                        @endphp
                        @if($isLink)
                            <a href="{{ route($tx->kind === 'order' ? 'orders.show' : 'purchases.show', $tx->id) }}" class="list-row group" data-kind="{{ $tx->kind }}" data-balance="{{ $balance }}">
                        @else
                            <div class="list-row" data-kind="{{ $tx->kind }}" data-balance="{{ $balance }}">
                        @endif
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="w-9 h-9 rounded-xl {{ $bubble }} text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                                        <x-icon name="{{ $icon }}" class="w-4 h-4 text-white"/>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $tx->label }}</div>
                                        <div class="text-[11px] text-ink-500 dark:text-ink-400">
                                            {{ $tx->date->format('H:i') }}
                                            @if($tx->notes)
                                                &middot; {{ $tx->notes }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2.5 flex-shrink-0 ms-3">
                                    <div class="text-end">
                                        <div class="text-sm font-bold {{ $tx->direction === 'in' ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}" dir="ltr">
                                            {{ $tx->direction === 'in' ? '+' : '-' }}{{ number_format((float) $tx->amount, 2) }}
                                        </div>
                                        <div class="text-[10px] text-ink-400">{{ $tx->currency }}</div>
                                    </div>
                                    {{-- Running balance: this line's cumulative result --}}
                                    <div class="text-end min-w-[4.5rem] ps-2.5 border-s border-ink-100 dark:border-ink-700/30">
                                        <div class="text-[9px] font-semibold uppercase tracking-wide text-ink-400 dark:text-ink-500">{{ __('messages.balance') }}</div>
                                        <div class="text-xs font-extrabold tabular-nums text-ink-700 dark:text-ink-200" dir="ltr">{{ number_format((float) $balance, 2) }}</div>
                                    </div>
                                    @if($isLink)
                                        <x-icon name="chevron-right" class="w-3.5 h-3.5 text-ink-300 group-hover:text-primary-500 transition-colors"/>
                                    @endif
                                </div>
                        @if($isLink)
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            @empty
                <x-empty-state title="{{ __('messages.no_person_transactions') }}">
                    <x-icon name="wallet" class="w-6 h-6 text-ink-400"/>
                </x-empty-state>
            @endforelse

            {{-- Closing balance bar: the last line's running balance, pinned --}}
            <div class="flex items-center justify-between px-4 py-3.5 bg-ink-50 dark:bg-ink-800/40 border-t border-ink-100 dark:border-ink-700/30 tabular-nums" data-closing="{{ $closing }}">
                <span class="text-xs font-bold text-ink-800 dark:text-ink-200">{{ __('messages.closing_balance') }} · {{ $selectedCurrency }}</span>
                <span class="text-sm font-extrabold {{ $closingPositive ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}" dir="ltr">
                    {{ $closingSign }}{{ number_format(abs((float) $closing), 2) }}
                </span>
            </div>
        </div>
    @else
        <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
            <x-empty-state title="{{ __('messages.no_person_transactions') }}">
                <x-icon name="wallet" class="w-6 h-6 text-ink-400"/>
            </x-empty-state>
        </div>
    @endif
@endsection

@push('scripts')
    @once
        <script>
            (function () {
                const tabs = document.querySelectorAll('#tx-tabs [data-filter]');
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
                        document.querySelectorAll('#tx-list [data-kind]').forEach(function (row) {
                            row.style.display = (filter === 'all' || row.dataset.kind === filter) ? '' : 'none';
                        });
                        document.querySelectorAll('#tx-list .tx-group').forEach(function (group) {
                            const anyVisible = Array.prototype.some.call(group.querySelectorAll('[data-kind]'), function (r) {
                                return r.style.display !== 'none';
                            });
                            group.style.display = anyVisible ? '' : 'none';
                        });
                    });
                });
            })();
        </script>
    @endonce
@endpush
