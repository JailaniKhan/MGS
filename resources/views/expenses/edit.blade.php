@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('expenses.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.edit_expense') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('expenses.update', $expense) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="form-label">{{ __('messages.categories') }}</label>
                <select name="category_select" id="category-select" class="form-input">
                    <option value="" disabled {{ old('category', $expense->category) ? '' : 'selected' }}>{{ __('messages.select_category') }}</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}" {{ (old('category', $expense->category) ?? '') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                    <option value="__other__" {{ old('category', $expense->category) && !in_array(old('category', $expense->category), $categories->toArray()) ? 'selected' : '' }}>{{ __('messages.other') }}</option>
                </select>
                <input type="text" name="category" id="category-input" value="{{ old('category', $expense->category) }}" required class="form-input mt-2 hidden" placeholder="{{ __('messages.expense_category') }}">
                @error('category') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency_unit') }}</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', $expense->currency) === 'AFN' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.afn') }} ({{ __('messages.afn') }})</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2.5 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-all duration-200">
                        <input type="radio" name="currency" value="USD" {{ old('currency', $expense->currency) === 'USD' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm text-ink-700 dark:text-ink-300">{{ __('messages.usd_with_paren') }}$)</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.amount') }}</label>
                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $expense->amount) }}" required class="form-input" placeholder="{{ __('messages.amount') }}">
                @error('amount') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.date') }}</label>
                <input type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required class="form-input">
                @error('expense_date') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.notes_optional') }}</label>
                <textarea name="notes" rows="2" class="form-input" placeholder="{{ __('messages.notes') }}">{{ old('notes', $expense->notes) }}</textarea>
                @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.receipt_optional') }}</label>
                <input type="text" name="receipt_path" value="{{ old('receipt_path', $expense->receipt_path) }}" class="form-input" placeholder="{{ __('messages.file_attachment') }}">
                @error('receipt_path') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.save') }}</button>
        </form>
    </div>

    @push('scripts')
    <script>
        (function () {
            const select = document.getElementById('category-select');
            const input = document.getElementById('category-input');
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
            sync();
        })();
    </script>
    @endpush
@endsection