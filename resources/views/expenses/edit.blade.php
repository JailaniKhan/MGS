@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('expenses.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.edit_expense') }}</h2>
    </div>

    <form action="{{ route('expenses.update', $expense) }}" method="POST">
        @csrf @method('PUT')

        {{-- Section: details --}}
        <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
            <div class="mb-4">
                <label class="form-label">{{ __('messages.categories') }}</label>
                <x-searchable-select name="category_select" select-class="category-select" required placeholder="{{ __('messages.select_category') }}" :selected="$pickerSelected" :options="$categoryOptions" />
                <input type="text" name="category" id="category-input" value="{{ old('category', $expense->category) }}" required class="form-input mt-2 hidden" placeholder="{{ __('messages.expense_category') }}">
                @error('category') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.date') }}</label>
                <div class="relative">
                    <input type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required class="form-input pe-11">
                    <x-icon name="calendar" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                </div>
                @error('expense_date') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">{{ __('messages.notes_optional') }}</label>
                <textarea name="notes" rows="2" class="form-input resize-none" placeholder="{{ __('messages.notes') }}">{{ old('notes', $expense->notes) }}</textarea>
                @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Section: payment --}}
        <div class="card p-4 mt-3 page-enter" style="animation-delay: 0.2s;">
            <div class="mb-4">
                <label class="form-label">{{ __('messages.amount') }}</label>
                <div class="relative" dir="ltr">
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $expense->amount) }}" required
                           dir="ltr" inputmode="decimal"
                           class="form-input text-2xl font-extrabold tabular-nums tracking-tight py-4 pe-16">
                    <span class="pointer-events-none absolute top-1/2 -translate-y-1/2 end-4 text-sm font-bold text-ink-400 dark:text-ink-500" id="amount-unit">{{ old('currency', $expense->currency) === 'USD' ? '$' : __('messages.afn') }}</span>
                </div>
                @error('amount') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', $expense->currency) === 'AFN' ? 'checked' : '' }} class="sr-only currency-radio">
                        <span>{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                        <input type="radio" name="currency" value="USD" {{ old('currency', $expense->currency) === 'USD' ? 'checked' : '' }} class="sr-only currency-radio">
                        <span>$</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Section: attachment --}}
        <div class="card p-4 mt-3 page-enter" style="animation-delay: 0.3s;">
            <div class="relative">
                <span class="form-label">{{ __('messages.receipt_optional') }}</span>
                <div class="relative" dir="ltr">
                    <input type="text" name="receipt_path" value="{{ old('receipt_path', $expense->receipt_path) }}" dir="ltr" class="form-input pe-11" placeholder="{{ __('messages.file_attachment') }}">
                    <x-icon name="link" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                </div>
                @error('receipt_path') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-4 page-enter" style="animation-delay: 0.4s;">
            <button type="submit" class="btn-primary w-full py-4">
                <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
                {{ __('messages.save') }}
            </button>
        </div>
    </form>

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
@endsection
