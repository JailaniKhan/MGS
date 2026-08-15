@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('cashbook.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_cashbook_entry') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form method="POST" action="{{ route('cashbook.store') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.type') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2.5 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/30 transition-colors">
                        <input type="radio" name="type" value="in" {{ old('type', 'in') === 'in' ? 'checked' : '' }} class="text-primary-600">
                        <x-icon name="plus" class="w-4 h-4 text-primary-600" strokeWidth="2.2"/>
                        <span class="text-sm font-bold text-primary-700 dark:text-primary-300">{{ __('messages.income_entry') }}</span>
                    </label>
                    <label class="flex items-center gap-2.5 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-danger-500 has-[:checked]:bg-danger-50 dark:has-[:checked]:bg-danger-900/20 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-danger-500/30 transition-colors">
                        <input type="radio" name="type" value="out" {{ old('type') === 'out' ? 'checked' : '' }} class="text-danger-600">
                        <x-icon name="minus" class="w-4 h-4 text-danger-600" strokeWidth="2.2"/>
                        <span class="text-sm font-bold text-danger-700 dark:text-danger-300">{{ __('messages.expense_entry') }}</span>
                    </label>
                </div>
                @error('type') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2.5 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/30 transition-colors">
                        <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm font-medium text-ink-800 dark:text-ink-100">{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center gap-2.5 px-4 py-3 border border-ink-200 dark:border-ink-700 rounded-xl cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/30 transition-colors">
                        <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="text-primary-600">
                        <span class="text-sm font-medium text-ink-800 dark:text-ink-100">USD ($)</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="form-label">{{ __('messages.amount_amount') }}</label>
                    <input type="number" step="0.01" min="0.01" inputmode="decimal" name="amount" value="{{ old('amount') }}" required placeholder="0.00" class="form-input">
                    @error('amount') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.date') }}</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', date('Y-m-d')) }}" required class="form-input">
                    @error('entry_date') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mb-4">
                <div class="flex items-center justify-between">
                    <label class="form-label">{{ __('messages.person') }}</label>
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-ink-400">{{ __('messages.optional') }}</span>
                </div>
                <x-searchable-select name="person" :selected="old('person')" placeholder="{{ __('messages.select_person') }}" :options="$personOptions" />
                @error('person') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-5">
                <label class="form-label">{{ __('messages.notes') }}</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="form-input" placeholder="{{ __('messages.notes') }}">
                @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn-primary w-full">
                <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
                {{ __('messages.register') }}
            </button>
        </form>
    </div>
@endsection
