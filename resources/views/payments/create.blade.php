@extends('layouts.app')

@section('content')
    @php
        // Party-scoped visits (from a ledger page) that only hold one document
        // type skip the order/purchase switcher entirely.
        $singleType = $partyType && ($orderPicker->isEmpty() || $purchasePicker->isEmpty());
    @endphp
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 border border-brand/20 dark:border-brand/30 flex items-center justify-center flex-shrink-0">
                <x-icon name="banknotes" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.new_payment') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $partyName ?? __('messages.payment_type') }} &middot; {{ __('messages.pending') }}</p>
            </div>
        </div>
        <x-back-button href="{{ $partyType ? route('ledger.show', [$partyType, $partyId]) : route('payments.index') }}"/>
    </div>

    <form action="{{ route('payments.store') }}" method="POST">
        @csrf
        @if ($partyType)
            <input type="hidden" name="return_to" value="ledger">
        @endif

        {{-- Payment details --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="document-text" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.payment_type') }}</h3>
                </div>
            </div>
            <div class="p-4">
                @php $currentType = old('type', $selectedType); @endphp
                @if (! $singleType)
                <div class="grid grid-cols-2 gap-2 mb-4">
                    @foreach ([
                        ['value' => 'order', 'label' => __('messages.order_payment')],
                        ['value' => 'purchase', 'label' => __('messages.purchase_payment')],
                    ] as $chip)
                        <button type="button" data-pay-type="{{ $chip['value'] }}"
                                class="px-4 py-3 rounded-xl text-sm font-bold transition-all duration-200 active:scale-95 {{ ($currentType === $chip['value']) ? 'bg-brand text-white shadow-[var(--shadow-btn)]' : 'bg-ink-50 dark:bg-white/[0.04] border border-ink-200 dark:border-white/[0.08] text-ink-600 dark:text-ink-300 hover:border-ink-300 dark:hover:border-white/[0.15]' }}">{{ $chip['label'] }}</button>
                    @endforeach
                </div>
                @endif
                <input type="hidden" name="type" id="type-input" value="{{ $currentType }}">
                @error('type') <p class="text-danger-500 text-[11px] mb-3">{{ $message }}</p> @enderror

                {{-- Searchable document picker --}}
                <div class="relative" id="doc-picker">
                    <label class="form-label" id="doc-label">{{ $selectedType === 'purchase' ? __('messages.purchase') : __('messages.order') }}</label>
                    <div class="relative">
                        <input type="search" id="doc-search" autocomplete="off" inputmode="search"
                               placeholder="{{ __('messages.search_document') }}"
                               class="form-input ps-10 pe-10">
                        <x-icon name="magnifying-glass" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                        <button type="button" id="doc-clear" aria-label="{{ __('messages.clear') }}"
                                class="hidden w-6 h-6 rounded-full bg-ink-100 dark:bg-white/[0.08] items-center justify-center text-ink-500 dark:text-ink-400 absolute top-1/2 -translate-y-1/2 end-3 active:scale-90 transition-transform">
                            <x-icon name="x-mark" class="w-3.5 h-3.5" strokeWidth="2"/>
                        </button>
                    </div>

                    <input type="hidden" name="order_id" id="order-id-input" value="{{ old('order_id', $selectedOrderId) }}">
                    <input type="hidden" name="purchase_id" id="purchase-id-input" value="{{ old('purchase_id', $selectedPurchaseId) }}">

                    <div id="doc-dropdown" class="hidden mt-2 rounded-xl border border-ink-100 dark:border-ink-700/30 overflow-hidden shadow-sm">
                        <div id="doc-list" class="max-h-72 overflow-y-auto divide-y divide-ink-100 dark:divide-ink-700/30"></div>
                        <div id="doc-empty" class="hidden px-4 py-8 text-center">
                            <x-icon name="magnifying-glass" class="w-6 h-6 text-ink-300 dark:text-ink-600 mx-auto mb-2" strokeWidth="1.5"/>
                            <p class="text-xs font-medium text-ink-400 dark:text-ink-500">{{ __('messages.no_results') }}</p>
                        </div>
                    </div>

                    <p id="doc-error" class="hidden text-danger-500 text-[11px] mt-1">{{ __('messages.doc_required') }}</p>
                    @error('order_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                    @error('purchase_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Selected document info --}}
                <div id="document-info" class="hidden mt-3 rounded-xl border border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40 p-4 space-y-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-white dark:bg-white/[0.05] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center flex-shrink-0">
                            <x-icon name="document-text" class="w-3.5 h-3.5 text-brand" strokeWidth="1.8"/>
                        </div>
                        <span id="info-title" class="text-xs font-bold text-ink-800 dark:text-ink-200 truncate"></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span id="party-label" class="text-ink-500 dark:text-ink-400" data-customer="{{ __('messages.customer') }}" data-supplier="{{ __('messages.supplier') }}"></span>
                        <span id="info-party" class="font-semibold text-ink-800 dark:text-ink-200 truncate ms-3"></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-ink-500 dark:text-ink-400">{{ __('messages.order_total') }}:</span>
                        <span id="info-total" class="font-semibold text-ink-800 dark:text-ink-200 tabular-nums ms-3"></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-ink-500 dark:text-ink-400">{{ __('messages.remaining_amount') }}:</span>
                        <span id="info-remaining" class="font-bold text-brand tabular-nums ms-3"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Amount --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="banknotes" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.currency_unit') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.payment_method') }}</label>
                    <div class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border border-brand bg-brand/10 dark:bg-brand/20 text-sm font-bold text-brand">
                        <x-icon name="banknotes" class="w-4 h-4" strokeWidth="1.8"/>
                        {{ __('messages.cash') }}
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 mb-4">
                    <x-radio-pill name="currency" value="AFN" :label="__('messages.afn')" :checked="old('currency', 'AFN') === 'AFN'" pill/>
                    <x-radio-pill name="currency" value="USD" :label="'$'" :checked="old('currency') === 'USD'" pill/>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] -mt-2 mb-4">{{ $message }}</p> @enderror

                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="form-label mb-0">{{ __('messages.payment_amount') }}</label>
                        <button type="button" id="fill-remaining" class="hidden text-[11px] font-semibold text-brand hover:text-primary-700 dark:hover:text-primary-300 transition-colors tabular-nums"></button>
                    </div>
                    <div class="relative" dir="ltr">
                        <input type="number" name="amount" id="amount-input" value="{{ old('amount') }}" step="0.01" min="0.01" required dir="ltr" inputmode="decimal" class="form-input text-2xl font-extrabold tabular-nums tracking-tight py-4 pe-14">
                        <span id="amount-unit" class="pointer-events-none absolute top-1/2 -translate-y-1/2 end-4 text-sm font-bold text-ink-400 dark:text-ink-500">{{ old('currency', 'AFN') === 'USD' ? '$' : __('messages.afn') }}</span>
                    </div>
                    @error('amount') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('messages.notes_optional') }}</label>
                    <div class="relative">
                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-input ps-10">
                        <x-icon name="document-text" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    </div>
                    @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full page-enter" style="animation-delay: 0.2s;">
            <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.record_payment') }}
        </button>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const T = {
            afn: @json(__('messages.afn')),
            order: @json(__('messages.order')),
            purchase: @json(__('messages.purchase')),
            pending: @json(__('messages.pending')),
        };

        const docs = {
            order: @json($orderPicker),
            purchase: @json($purchasePicker),
        };

        const typeChips = Array.from(document.querySelectorAll('[data-pay-type]'));
        const searchInput = document.getElementById('doc-search');
        const clearBtn = document.getElementById('doc-clear');
        const dropdown = document.getElementById('doc-dropdown');
        const listEl = document.getElementById('doc-list');
        const emptyEl = document.getElementById('doc-empty');
        const docLabel = document.getElementById('doc-label');
        const hiddenOrder = document.getElementById('order-id-input');
        const hiddenPurchase = document.getElementById('purchase-id-input');
        const typeInput = document.getElementById('type-input');
        const infoBox = document.getElementById('document-info');
        const infoTitle = document.getElementById('info-title');
        const partyLabel = document.getElementById('party-label');
        const infoParty = document.getElementById('info-party');
        const infoTotal = document.getElementById('info-total');
        const infoRemaining = document.getElementById('info-remaining');
        const docError = document.getElementById('doc-error');
        const amountInput = document.getElementById('amount-input');
        const amountUnit = document.getElementById('amount-unit');
        const fillRemaining = document.getElementById('fill-remaining');
        const currencyRadios = Array.from(document.querySelectorAll('input[name="currency"]'));

        let type = 'order';
        let selectedId = null;
        let selectedDoc = null;

        function fmt(n) {
            return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(n);
        }

        function symbol(currency) {
            return currency === 'USD' ? '$' : T.afn;
        }

        function activeDocs() {
            return docs[type];
        }

        function hiddenInput() {
            return type === 'order' ? hiddenOrder : hiddenPurchase;
        }

        function otherHiddenInput() {
            return type === 'order' ? hiddenPurchase : hiddenOrder;
        }

        function setType(t) {
            type = t;
            typeInput.value = t;
            docLabel.textContent = t === 'purchase' ? T.purchase : T.order;
            typeChips.forEach(function (chip) {
                const on = chip.dataset.payType === t;
                chip.className = 'px-4 py-3 rounded-xl text-sm font-bold transition-all duration-200 active:scale-95 ' + (on
                    ? 'bg-brand text-white shadow-[var(--shadow-btn)]'
                    : 'bg-ink-50 dark:bg-white/[0.04] border border-ink-200 dark:border-white/[0.08] text-ink-600 dark:text-ink-300 hover:border-ink-300 dark:hover:border-white/[0.15]');
            });
            clearSelection();
        }

        function rowFor(doc) {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'w-full text-start px-4 py-3 flex items-center justify-between gap-3 hover:bg-ink-50 dark:hover:bg-white/[0.04] transition-colors active:bg-ink-100 dark:active:bg-white/[0.06]';
            row.innerHTML =
                '<span class="min-w-0">' +
                    '<span class="block text-sm font-semibold text-ink-800 dark:text-ink-200 truncate">#' + doc.id + ' &middot; ' + escapeHtml(doc.party) + '</span>' +
                    '<span class="block text-[10px] text-ink-400 dark:text-ink-500 mt-0.5">' + (type === 'order' ? T.order : T.purchase) + '</span>' +
                '</span>' +
                '<span class="flex-shrink-0 px-2.5 py-1 rounded-full text-[10px] font-bold bg-brand/10 dark:bg-brand/20 text-brand tabular-nums">' + T.pending + ': ' + fmt(doc.remaining) + ' ' + symbol(doc.currency) + '</span>';
            row.addEventListener('pointerdown', function (e) {
                e.preventDefault();
                selectDoc(doc);
            });
            return row;
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function renderList() {
            const q = searchInput.value.trim().toLowerCase();
            const list = activeDocs().filter(function (doc) {
                if (!q) return true;
                return ('#' + doc.id).includes(q) || doc.party.toLowerCase().includes(q) || String(doc.id).includes(q);
            });
            listEl.innerHTML = '';
            list.forEach(function (doc) { listEl.appendChild(rowFor(doc)); });
            emptyEl.classList.toggle('hidden', list.length > 0);
            dropdown.classList.toggle('hidden', selectedId !== null);
        }

        function openDropdown() {
            if (selectedId === null) {
                renderList();
                dropdown.classList.remove('hidden');
            }
        }

        function closeDropdown() {
            dropdown.classList.add('hidden');
        }

        function selectDoc(doc) {
            selectedId = doc.id;
            selectedDoc = doc;
            otherHiddenInput().value = '';
            hiddenInput().value = doc.id;
            searchInput.value = '#' + doc.id + ' — ' + doc.party;
            clearBtn.classList.remove('hidden');
            clearBtn.classList.add('flex');
            closeDropdown();
            docError.classList.add('hidden');

            infoTitle.textContent = (type === 'order' ? T.order : T.purchase) + ' #' + doc.id;
            partyLabel.textContent = type === 'purchase' ? partyLabel.dataset.supplier : partyLabel.dataset.customer;
            infoParty.textContent = doc.party;
            infoTotal.textContent = fmt(doc.total) + ' ' + symbol(doc.currency);
            infoRemaining.textContent = fmt(doc.remaining) + ' ' + symbol(doc.currency);
            infoBox.classList.remove('hidden');

            amountInput.max = doc.remaining;
            const currencyInput = document.querySelector('input[name="currency"][value="' + doc.currency + '"]');
            if (currencyInput) currencyInput.checked = true;
            amountUnit.textContent = symbol(doc.currency);
            fillRemaining.textContent = T.pending + ': ' + fmt(doc.remaining) + ' ' + symbol(doc.currency);
            fillRemaining.classList.remove('hidden');
        }

        function clearSelection() {
            selectedId = null;
            selectedDoc = null;
            hiddenOrder.value = '';
            hiddenPurchase.value = '';
            searchInput.value = '';
            clearBtn.classList.add('hidden');
            clearBtn.classList.remove('flex');
            infoBox.classList.add('hidden');
            docError.classList.add('hidden');
            amountInput.max = '';
            fillRemaining.classList.add('hidden');
            renderList();
            openDropdown();
        }

        function syncUnitFromRadios() {
            const checked = currencyRadios.find(function (r) { return r.checked; });
            if (checked) amountUnit.textContent = symbol(checked.value);
        }
        currencyRadios.forEach(function (r) { r.addEventListener('change', syncUnitFromRadios); });

        fillRemaining.addEventListener('click', function () {
            if (selectedDoc === null) return;
            amountInput.value = selectedDoc.remaining;
            amountInput.focus();
        });

        searchInput.addEventListener('focus', openDropdown);
        searchInput.addEventListener('input', function () {
            if (selectedId !== null) clearSelection();
            renderList();
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDropdown();
        });
        clearBtn.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            clearSelection();
            searchInput.focus();
        });

        document.addEventListener('pointerdown', function (e) {
            if (!document.getElementById('doc-picker').contains(e.target)) closeDropdown();
        });

        typeChips.forEach(function (chip) {
            chip.addEventListener('click', function () { setType(chip.dataset.payType); });
        });

        const form = document.querySelector('form[action="{{ route('payments.store') }}"]');
        form.addEventListener('submit', function (e) {
            if (selectedId === null) {
                docError.classList.remove('hidden');
                closeDropdown();
                searchInput.focus();
                e.preventDefault();
            }
        });

        const initialOrder = hiddenOrder.value;
        const initialPurchase = hiddenPurchase.value;
        if (initialOrder) {
            setType('order');
            const doc = docs.order.find(function (d) { return String(d.id) === initialOrder; });
            if (doc) { searchInput.value = ''; selectDoc(doc); }
        } else if (initialPurchase) {
            setType('purchase');
            const doc = docs.purchase.find(function (d) { return String(d.id) === initialPurchase; });
            if (doc) { searchInput.value = ''; selectDoc(doc); }
        } else {
            setType(typeInput.value === 'purchase' ? 'purchase' : 'order');
        }
        closeDropdown();
    })();
</script>
@endpush