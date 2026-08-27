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

        {{-- Section: details --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="tag" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.details') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.categories') }}</label>
                    <x-searchable-select name="category_select" select-class="category-select" required placeholder="{{ __('messages.select_category') }}" :selected="$pickerSelected" :options="$categoryOptions" />
                    <input type="text" name="category" id="category-input" value="{{ old('category') }}" required class="form-input mt-2 hidden" placeholder="{{ __('messages.expense_category') }}">
                    @error('category') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">{{ __('messages.date') }}</label>
                    <div class="relative" dir="ltr">
                        <input type="date" name="expense_date" value="{{ old('expense_date', date('Y-m-d')) }}" required dir="ltr" class="form-input pe-11">
                        <x-icon name="calendar" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                    </div>
                    @error('expense_date') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('messages.notes_optional') }}</label>
                    <textarea name="notes" rows="2" class="form-input resize-none" placeholder="{{ __('messages.notes') }}">{{ old('notes') }}</textarea>
                    @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Section: amount --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="banknotes" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.amount') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.currency_unit') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                            <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="sr-only currency-radio">
                            <span>{{ __('messages.afn') }}</span>
                        </label>
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                            <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="sr-only currency-radio">
                            <span>$</span>
                        </label>
                    </div>
                    @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('messages.amount') }}</label>
                    <div class="relative" dir="ltr">
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required
                               dir="ltr" inputmode="decimal"
                               class="form-input text-2xl font-extrabold tabular-nums tracking-tight py-4 pe-14">
                        <span class="pointer-events-none absolute top-1/2 -translate-y-1/2 end-4 text-sm font-bold text-ink-400 dark:text-ink-500" id="amount-unit">{{ old('currency', 'AFN') === 'USD' ? '$' : __('messages.afn') }}</span>
                    </div>
                    @error('amount') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Section: attachment --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.3s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="paper-clip" class="w-4 h-4 text-accent-600 dark:text-accent-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.receipt_optional') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="relative" dir="ltr">
                    <input type="text" name="receipt_path" value="{{ old('receipt_path') }}" dir="ltr" class="form-input pe-11" placeholder="{{ __('messages.file_attachment') }}">
                    <x-icon name="link" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                </div>
                @error('receipt_path') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit" class="btn-primary w-full page-enter" style="animation-delay: 0.4s;">
            <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
            {{ __('messages.submit') }}
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
</script>
@endpush
