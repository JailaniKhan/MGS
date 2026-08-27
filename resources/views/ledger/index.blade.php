@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 border border-brand/20 dark:border-brand/30 flex items-center justify-center flex-shrink-0">
                <x-icon name="book-open" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.ledger') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $counts['customer'] }} {{ __('messages.customers') }} &middot; {{ $counts['supplier'] }} {{ __('messages.suppliers') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('dashboard') }}"/>
    </div>

    {{-- Outstanding totals --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-brand/[0.08] dark:bg-brand/[0.09]"></div>
        <div class="relative grid grid-cols-2 gap-4">
            <div>
                <div class="flex items-center gap-1.5 mb-1.5">
                    <div class="w-6 h-6 rounded-lg bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center">
                        <x-icon name="inbox-arrow-down" class="w-3.5 h-3.5 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
                    </div>
                    <span class="metric-label">{{ __('messages.customer_receivables') }}</span>
                </div>
                @if ((float) $totals['customer']['USD'] > 0)
                    <p class="text-sm font-bold tabular-nums text-ink-400 dark:text-ink-500"><bdi>${{ number_format((float) $totals['customer']['USD'], 2) }}</bdi></p>
                @endif
                <p class="text-xl font-extrabold tabular-nums tracking-tight text-primary-600 dark:text-primary-400"><bdi>{{ number_format((float) $totals['customer']['AFN'], 2) }}</bdi> <span class="text-[11px] font-bold text-ink-400 dark:text-ink-500">{{ __('messages.afn') }}</span></p>
            </div>
            <div>
                <div class="flex items-center gap-1.5 mb-1.5">
                    <div class="w-6 h-6 rounded-lg bg-danger-50 dark:bg-danger-900/30 flex items-center justify-center">
                        <x-icon name="truck" class="w-3.5 h-3.5 text-danger-500 dark:text-danger-400" strokeWidth="1.8"/>
                    </div>
                    <span class="metric-label">{{ __('messages.supplier_payables') }}</span>
                </div>
                @if ((float) $totals['supplier']['USD'] > 0)
                    <p class="text-sm font-bold tabular-nums text-ink-400 dark:text-ink-500"><bdi>${{ number_format((float) $totals['supplier']['USD'], 2) }}</bdi></p>
                @endif
                <p class="text-xl font-extrabold tabular-nums tracking-tight text-danger-600 dark:text-danger-400"><bdi>{{ number_format((float) $totals['supplier']['AFN'], 2) }}</bdi> <span class="text-[11px] font-bold text-ink-400 dark:text-ink-500">{{ __('messages.afn') }}</span></p>
            </div>
        </div>
    </div>

    {{-- Type filter chips --}}
    <div class="flex gap-2 overflow-x-auto pb-1 mb-3 page-enter" style="animation-delay: 0.08s;">
        @foreach ([
            ['value' => 'all', 'label' => __('messages.all')],
            ['value' => 'customer', 'label' => __('messages.customers')],
            ['value' => 'supplier', 'label' => __('messages.suppliers')],
        ] as $chip)
            <button type="button" data-ledger-type="{{ $chip['value'] }}"
                    onclick="ledgerFilter.setType(this.dataset.ledgerType)"
                    class="whitespace-nowrap px-4 py-2 rounded-full text-xs font-bold transition-all duration-200 active:scale-95 {{ $chip['value'] === 'all' ? 'bg-brand text-white shadow-[var(--shadow-btn)]' : 'bg-ink-50 dark:bg-white/[0.04] border border-ink-200 dark:border-white/[0.08] text-ink-600 dark:text-ink-300 hover:border-ink-300 dark:hover:border-white/[0.15]' }}">{{ $chip['label'] }}</button>
        @endforeach
    </div>

    {{-- Search --}}
    <x-search-filter-bar id="ledger-search-bar" data-list-filter="ledger-none" :empty-text="__('messages.no_results')" class="page-enter" style="animation-delay: 0.1s;" />

    {{-- People list --}}
    <div id="ledger-list" class="card overflow-hidden page-enter" style="animation-delay: 0.12s;">
        @forelse ($people as $person)
            @php
                $initial = mb_substr(trim($person['name']), 0, 1);
                $isCustomer = $person['type'] === 'customer';
                $hasBalance = $person['remaining_afn'] > 0 || $person['remaining_usd'] > 0;
            @endphp
            <a href="{{ route('ledger.show', [$person['type'], $person['id']]) }}" class="list-row" data-party-type="{{ $person['type'] }}" data-party-name="{{ strtolower($person['name']) }}">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-10 h-10 rounded-xl {{ $isCustomer ? 'bg-primary-50 dark:bg-primary-900/25 text-primary-600 dark:text-primary-400' : 'bg-danger-50 dark:bg-danger-900/25 text-danger-500 dark:text-danger-400' }} flex items-center justify-center flex-shrink-0">
                        <span class="text-sm font-extrabold">{{ $initial }}</span>
                    </div>
                    <div class="min-w-0">
                        {{-- Type lives in the avatar tint, the amounts colour and the
                            filter chips — the row itself stays name-first so long
                            names never truncate. --}}
                        <div class="text-sm font-semibold text-ink-800 dark:text-ink-200 truncate">{{ $person['name'] }}</div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-ink-100 dark:bg-white/[0.06] text-ink-600 dark:text-ink-300 tabular-nums" title="{{ __('messages.documents') }}">
                                <x-icon name="clipboard-document-list" class="w-3 h-3" strokeWidth="2"/>
                                {{ $person['total_documents'] }}
                            </span>
                            @if ($person['phone'])
                                <span class="text-[11px] text-ink-400 dark:text-ink-500 tabular-nums" dir="ltr">{{ $person['phone'] }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-end flex-shrink-0 ms-3">
                    @if ($hasBalance)
                        @if ($person['remaining_afn'] > 0)
                            <div class="text-sm font-bold tabular-nums {{ $isCustomer ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}"><bdi>{{ number_format((float) $person['remaining_afn'], 0) }}</bdi> {{ __('messages.afn') }}</div>
                        @endif
                        @if ($person['remaining_usd'] > 0)
                            <div class="text-sm font-bold tabular-nums {{ $isCustomer ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}"><bdi>${{ number_format((float) $person['remaining_usd'], 0) }}</bdi></div>
                        @endif
                    @else
                        <span class="badge badge-success"><x-icon name="check-circle" class="w-3.5 h-3.5"/>{{ __('messages.fully_paid') }}</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="empty-state">
                <p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_customers_or_suppliers') }}</p>
            </div>
        @endforelse
    </div>

    @if ($people->hasPages())
        <div class="mt-4">{{ $people->links() }}</div>
    @endif
@endsection

@push('scripts')
<script>
    window.ledgerFilter = (function () {
        const list = document.getElementById('ledger-list');
        if (!list) return { setType() {}, };

        const rows = Array.from(list.querySelectorAll('.list-row'));
        const searchInput = document.querySelector('#ledger-search-bar input');
        const chips = Array.from(document.querySelectorAll('[data-ledger-type]'));
        let type = 'all';

        function apply() {
            const q = searchInput ? searchInput.value.trim().toLowerCase() : '';
            let visible = 0;
            rows.forEach((row) => {
                const typeOk = type === 'all' || row.dataset.partyType === type;
                const textOk = !q || row.dataset.partyName.includes(q) || row.textContent.toLowerCase().includes(q);
                const show = typeOk && textOk;
                row.classList.toggle('hidden', !show);
                if (show) visible++;
            });

            let empty = list.querySelector('.filter-empty');
            if (visible === 0 && rows.length > 0) {
                if (!empty) {
                    const el = document.createElement('div');
                    el.className = 'filter-empty empty-state';
                    el.innerHTML = '<p class="text-sm font-medium text-ink-500 dark:text-ink-400">' + (searchInput && searchInput.dataset.emptyText || 'No results') + '</p>';
                    list.appendChild(el);
                }
            } else if (empty) {
                empty.remove();
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', apply);
        }

        return {
            setType(value) {
                type = value;
                chips.forEach((chip) => {
                    const active = chip.dataset.ledgerType === value;
                    chip.className = active
                        ? 'whitespace-nowrap px-4 py-2 rounded-full text-xs font-bold transition-all duration-200 active:scale-95 bg-brand text-white shadow-[var(--shadow-btn)]'
                        : 'whitespace-nowrap px-4 py-2 rounded-full text-xs font-bold transition-all duration-200 active:scale-95 bg-ink-50 dark:bg-white/[0.04] border border-ink-200 dark:border-white/[0.08] text-ink-600 dark:text-ink-300 hover:border-ink-300 dark:hover:border-white/[0.15]';
                });
                apply();
            }
        };
    })();
</script>
@endpush
