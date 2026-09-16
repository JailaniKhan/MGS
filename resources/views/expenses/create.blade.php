@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="banknotes" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.new_expense') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.expense_date') }} &middot; <bdi>{{ local_date(now(), 'd M Y') }}</bdi></p>
            </div>
        </div>
        <x-back-button href="{{ route('expenses.index') }}"/>
    </div>

    <form action="{{ route('expenses.store') }}" method="POST">
        @csrf

        {{-- Hero: the amount first. Money going out reads amber, like the
            purchase totals block; the number is the anchor of this form. --}}
        <div class="relative bg-accent-500/[0.06] dark:bg-accent-500/[0.08] border border-accent-500/20 dark:border-accent-500/25 rounded-2xl p-4 mb-4 overflow-hidden page-enter" style="animation-delay: 0.05s;">
            <div class="pointer-events-none absolute -top-12 -end-10 w-40 h-40 rounded-full bg-accent-500/10 dark:bg-accent-400/[0.07]"></div>

            <div class="relative">
                <span class="form-label !tracking-[0.14em]">{{ __('messages.amount') }}</span>
                <div class="relative" dir="ltr">
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required
                           dir="ltr" inputmode="decimal" id="amount-input"
                           class="w-full bg-transparent border-0 border-b-2 border-accent-500/30 focus:border-accent-500 dark:focus:border-accent-400 text-4xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-white py-2.5 px-0 focus:outline-none focus:ring-0 placeholder:text-ink-300/60 dark:placeholder:text-ink-600"
                           placeholder="0.00">
                    <span class="pointer-events-none absolute bottom-4 end-1 text-xl font-bold text-accent-600/70 dark:text-accent-400/70" id="amount-unit">{{ old('currency', 'AFN') === 'USD' ? '$' : __('messages.afn') }}</span>
                </div>
                @error('amount') <p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p> @enderror

                {{-- Currency: segmented control over the same radios (POST shape
                    and has-[:checked] styling contract stay untouched). --}}
                <div class="segmented mt-4" role="radiogroup" aria-label="{{ __('messages.currency_unit') }}">
                    <label class="segmented-item has-[:checked]:segmented-item-active cursor-pointer">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="sr-only currency-radio">
                        <span>{{ __('messages.afn') }}</span>
                    </label>
                    <label class="segmented-item has-[:checked]:segmented-item-active cursor-pointer">
                        <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="sr-only currency-radio">
                        <span dir="ltr">$</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Core details: category + date, one quiet card --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.categories') }}</label>
                    <x-searchable-select name="category_select" select-class="category-select" required placeholder="{{ __('messages.select_category') }}" :selected="$pickerSelected" :options="$categoryOptions" />
                    <input type="text" name="category" id="category-input" value="{{ old('category') }}" required class="form-input mt-2 hidden" placeholder="{{ __('messages.expense_category') }}">
                    @error('category') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label" for="expense-date">{{ __('messages.date') }}</label>
                    <div class="relative" dir="ltr">
                        <input type="date" id="expense-date" name="expense_date" value="{{ old('expense_date', date('Y-m-d')) }}" required dir="ltr" class="form-input pe-11">
                        <x-icon name="calendar" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                    </div>
                    @error('expense_date') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Attach to purchase / lot (landed cost). The card wakes up — amber
            border + active chip — once a purchase is actually picked.
            NO overflow-hidden: the searchable-select dropdown must be able to
            overhang the card edge. --}}
        <div class="card mb-4 page-enter transition-colors duration-300 border-accent-500/0 has-[#purchase-state.active]:border-accent-500/40" id="attach-card" style="animation-delay: 0.15s;">
            <div class="px-4 py-3 flex items-center gap-2 border-b border-ink-100 dark:border-ink-700/30">
                <x-icon name="truck" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200 flex-1 truncate">{{ __('messages.attach_to_purchase') }}</h3>
            </div>
            <div class="p-4">
                <x-searchable-select name="purchase_id" select-class="purchase-select" placeholder="{{ __('messages.no_purchase') }}" :selected="old('purchase_id')" :options="$purchaseOptions" />
                <input type="hidden" name="_purchase_currency" id="purchase-currency" value="">
                @error('purchase_id') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror

                <div id="purchase-state" class="hidden mt-3">
                    <div class="inline-flex max-w-full items-center gap-2 rounded-full bg-accent-500/10 dark:bg-accent-500/15 border border-accent-500/25 dark:border-accent-500/30 px-3 py-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-accent-500 animate-pulse flex-shrink-0"></span>
                        <span id="purchase-state-text" class="text-[11px] font-bold text-accent-700 dark:text-accent-300 truncate tabular-nums"></span>
                        <button type="button" id="purchase-state-clear" class="flex-shrink-0 text-accent-500/70 hover:text-danger-500 transition-colors" aria-label="{{ __('messages.remove') }}">
                            <x-icon name="x-mark" class="w-3.5 h-3.5" strokeWidth="2.2"/>
                        </button>
                    </div>
                </div>

                <p class="text-[11px] text-ink-400 dark:text-ink-500 mt-2.5 leading-relaxed">{{ __('messages.attach_to_purchase_hint') }}</p>
            </div>
        </div>

        {{-- Optional: notes + receipt share one muted card. No header, no
            icons — they are secondary on purpose. --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
            <div class="p-4">
                <div class="mb-4">
                    <label class="row-label !text-ink-500 dark:!text-ink-400 !text-[11px] !mb-1.5">{{ __('messages.notes_optional') }}</label>
                    <textarea name="notes" rows="2" class="form-input resize-none" placeholder="{{ __('messages.notes') }}">{{ old('notes') }}</textarea>
                    @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="row-label !text-ink-500 dark:!text-ink-400 !text-[11px] !mb-1.5">{{ __('messages.receipt_optional') }}</label>
                    <div class="relative" dir="ltr">
                        <input type="text" name="receipt_path" value="{{ old('receipt_path') }}" dir="ltr" class="form-input pe-11" placeholder="{{ __('messages.file_attachment') }}">
                        <x-icon name="link" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                    </div>
                    @error('receipt_path') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full page-enter" style="animation-delay: 0.25s;">
            <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
            {{ __('messages.save_expense') }}
        </button>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const select = document.querySelector('select.category-select');
        const input = document.getElementById('category-input');
        const unit = document.getElementById('amount-unit');
        if (!select || !input) return;

        function sync() {
            if (select.value === '__other__') {
                input.classList.remove('hidden');
                input.setAttribute('required', 'required');
            } else {
                input.classList.add('hidden');
                input.removeAttribute('required');
                input.value = select.value;
            }
        }

        select.addEventListener('change', sync);
        document.querySelector('form').addEventListener('submit', function () {
            if (select.value !== '__other__') input.value = select.value;
        });

        document.querySelectorAll('.currency-radio').forEach(function (radio) {
            radio.addEventListener('change', function () {
                unit.textContent = radio.value === 'USD' ? '$' : @json(__('messages.afn'));
            });
        });

        sync();
    })();

    // Purchase link (landed cost): picking a purchase locks the currency to
    // the purchase's own currency and confirms the link with an active chip.
    (function () {
        const purchaseSelect = document.querySelector('select.purchase-select');
        const purchaseCurrency = document.getElementById('purchase-currency');
        const state = document.getElementById('purchase-state');
        const stateText = document.getElementById('purchase-state-text');
        const stateClear = document.getElementById('purchase-state-clear');
        if (!purchaseSelect) return;

        function label() {
            const option = purchaseSelect.options[purchaseSelect.selectedIndex];
            if (!option) return '';

            const parts = [];
            const lots = option.getAttribute('data-lots');
            const products = option.getAttribute('data-products');
            const id = option.getAttribute('data-purchase-id') || purchaseSelect.value;

            if (lots) parts.push(@json(__('messages.lot_short')) + ' ' + lots);
            if (products) parts.push(products);
            if (id) parts.push('#' + id);

            return parts.join(' · ');
        }

        function sync() {
            const option = purchaseSelect.options[purchaseSelect.selectedIndex];
            const currency = option ? (option.getAttribute('data-price-currency') || '') : '';
            const picked = !!currency && purchaseSelect.value !== '';

            purchaseCurrency.value = picked ? currency : '';

            if (picked) {
                stateText.textContent = label();
                state.classList.remove('hidden');
                state.classList.add('active');
            } else {
                stateText.textContent = '';
                state.classList.add('hidden');
                state.classList.remove('active');
            }

            if (!picked || !currency) return;

            document.querySelectorAll('.currency-radio').forEach(function (radio) {
                if (radio.value === currency) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                } else {
                    radio.checked = false;
                }
            });
        }

        purchaseSelect.addEventListener('change', sync);

        if (stateClear) {
            stateClear.addEventListener('click', function () {
                purchaseSelect.value = '';
                purchaseSelect.dispatchEvent(new Event('change'));
            });
        }

        sync();
    })();
</script>
@endpush
