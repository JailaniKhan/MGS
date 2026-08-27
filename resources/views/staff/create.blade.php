@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-500/10 dark:bg-secondary-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="user-plus" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.new_staff') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.staff_book') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('staff.index') }}"/>
    </div>

    <form method="POST" action="{{ route('staff.store') }}">
        @csrf

        {{-- Section: person --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="identification" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.details') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.name') }}</label>
                    <div class="relative">
                        <input type="text" name="name" value="{{ old('name') }}" required class="form-input ps-10">
                        <x-icon name="user" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    </div>
                    @error('name')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">{{ __('messages.phone') }}</label>
                    <div class="relative" dir="ltr">
                        <input type="text" name="phone" value="{{ old('phone') }}" dir="ltr" class="form-input ps-10" placeholder="07xxxxxxxx">
                        <x-icon name="phone" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    </div>
                    @error('phone')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="form-label">{{ __('messages.position') }}</label>
                    <div class="relative">
                        <input type="text" name="position" value="{{ old('position') }}" class="form-input ps-10">
                        <x-icon name="briefcase" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    </div>
                    @error('position')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Section: salary --}}
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
                <div class="flex items-center gap-2">
                    <x-icon name="banknotes" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.monthly_salary') }}</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.currency') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                            <input type="radio" name="currency" value="AFN" @checked(old('currency', 'AFN') === 'AFN') class="sr-only staff-currency-radio">
                            <span>{{ __('messages.afn') }}</span>
                        </label>
                        <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                            <input type="radio" name="currency" value="USD" @checked(old('currency') === 'USD') class="sr-only staff-currency-radio">
                            <span>$</span>
                        </label>
                    </div>
                    @error('currency')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="form-label">{{ __('messages.monthly_salary') }}</label>
                    <div class="relative" dir="ltr">
                        <input type="number" step="0.01" min="0" name="monthly_salary" value="{{ old('monthly_salary') }}" required
                               dir="ltr" inputmode="decimal"
                               class="form-input text-2xl font-extrabold tabular-nums tracking-tight py-4 pe-14">
                        <span id="staff-salary-unit" class="pointer-events-none absolute top-1/2 -translate-y-1/2 end-4 text-sm font-bold text-ink-400 dark:text-ink-500">{{ old('currency', 'AFN') === 'USD' ? '$' : __('messages.afn') }}</span>
                    </div>
                    @error('monthly_salary')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full page-enter" style="animation-delay: 0.3s;">
            <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
            {{ __('messages.register') }}
        </button>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const unit = document.getElementById('staff-salary-unit');
        if (!unit) return;
        document.querySelectorAll('.staff-currency-radio').forEach(function (radio) {
            radio.addEventListener('change', function () {
                unit.textContent = radio.value === 'USD' ? '$' : @json(__('messages.afn'));
            });
        });
    })();
</script>
@endpush
