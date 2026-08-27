@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-primary-500/10 dark:bg-primary-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="wallet" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.new_cashbook_entry') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.cashbook') }} &middot; {{ __('messages.income_entry') }} / {{ __('messages.expense_entry') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('cashbook.index') }}"/>
    </div>

    <form method="POST" action="{{ route('cashbook.store') }}">
        @csrf

        {{-- Section: type & currency --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="arrows-right-left" class="w-4 h-4 text-primary-600 dark:text-primary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.type') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                            <input type="radio" name="type" value="in" {{ old('type', 'in') === 'in' ? 'checked' : '' }} class="sr-only">
                            <x-icon name="plus" class="w-4 h-4" strokeWidth="2.2"/>
                            <span>{{ __('messages.income_entry') }}</span>
                        </label>
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-danger-500 has-[:checked]:bg-danger-50 dark:has-[:checked]:bg-danger-900/20 has-[:checked]:text-danger-600 dark:has-[:checked]:text-danger-400 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-danger-500/30">
                            <input type="radio" name="type" value="out" {{ old('type') === 'out' ? 'checked' : '' }} class="sr-only">
                            <x-icon name="minus" class="w-4 h-4" strokeWidth="2.2"/>
                            <span>{{ __('messages.expense_entry') }}</span>
                        </label>
                    </div>
                    @error('type') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('messages.currency') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                            <input type="radio" name="currency" value="AFN" {{ old('currency', 'AFN') === 'AFN' ? 'checked' : '' }} class="sr-only">
                            <span>{{ __('messages.afn') }}</span>
                        </label>
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                            <input type="radio" name="currency" value="USD" {{ old('currency') === 'USD' ? 'checked' : '' }} class="sr-only">
                            <span>$</span>
                        </label>
                    </div>
                    @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Section: amount & details --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="banknotes" class="w-4 h-4 text-primary-600 dark:text-primary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.amount_amount') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.amount_amount') }}</label>
                    <div class="relative">
                        <input type="number" step="0.01" min="0.01" inputmode="decimal" dir="ltr" name="amount" value="{{ old('amount') }}" required placeholder="0.00"
                               class="form-input text-2xl font-extrabold tabular-nums tracking-tight py-4">
                    </div>
                    @error('amount') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">{{ __('messages.date') }}</label>
                    <div class="relative" dir="ltr">
                        <input type="date" name="entry_date" value="{{ old('entry_date', date('Y-m-d')) }}" required dir="ltr" class="form-input pe-11">
                        <x-icon name="calendar" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                    </div>
                    @error('entry_date') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="form-label mb-0">{{ __('messages.person') }}</label>
                        <span class="text-[10px] font-bold uppercase tracking-wide text-ink-400">{{ __('messages.optional') }}</span>
                    </div>
                    <x-searchable-select name="person" :selected="old('person')" placeholder="{{ __('messages.select_person') }}" :options="$personOptions" />
                    @error('person') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('messages.notes') }}</label>
                    <div class="relative">
                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-input ps-10" placeholder="{{ __('messages.notes') }}">
                        <x-icon name="document-text" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    </div>
                    @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full page-enter" style="animation-delay: 0.3s;">
            <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
            {{ __('messages.register') }}
        </button>
    </form>
@endsection
