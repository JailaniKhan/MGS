@extends('layouts.app')

@php
    $payments = $employee->salaryPayments;
    $paidTotal = $payments->sum('amount');
@endphp

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-500/10 dark:bg-secondary-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="identification" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight truncate">{{ $employee->name }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.staff_member') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('staff.index') }}"/>
    </div>

    {{-- Profile card --}}
    <div class="card relative overflow-hidden p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-secondary-500/[0.08] dark:bg-secondary-400/[0.08]"></div>
        <div class="relative flex items-center gap-3.5">
            <div class="w-14 h-14 rounded-2xl brand-grad text-white flex items-center justify-center flex-shrink-0 shadow-fab">
                <span class="text-xl font-extrabold">{{ mb_substr($employee->name, 0, 1) }}</span>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-base font-bold text-ink-900 dark:text-white truncate">{{ $employee->name }}</h3>
                <p class="text-xs text-ink-500 dark:text-ink-400 truncate">
                    {{ $employee->position ?: __('messages.position') }}
                    @if ($employee->phone) &middot; <span dir="ltr">{{ $employee->phone }}</span> @endif
                </p>
                <p class="mt-1.5 text-lg font-extrabold tabular-nums text-secondary-600 dark:text-secondary-400">
                    <x-money :amount="$employee->monthly_salary" :currency="$employee->currency" symbol-class="text-xs font-bold text-ink-400 dark:text-ink-500"/>
                    <span class="text-[10px] font-semibold text-ink-400 dark:text-ink-500">/ {{ __('messages.for_month') }}</span>
                </p>
            </div>
        </div>
        <div class="relative flex gap-2 mt-4 pt-3 border-t border-ink-100 dark:border-ink-700/30">
            <a href="{{ route('staff.edit', $employee) }}" class="btn-ghost btn-sm flex-1 justify-center"><x-icon name="pencil" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.edit') }}</a>
            <form action="{{ route('staff.destroy', $employee) }}" method="POST" class="flex-1" onsubmit="return confirm('{{ __('messages.are_you_sure') }}')">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger btn-sm w-full justify-center"><x-icon name="trash" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.delete') }}</button>
            </form>
        </div>
    </div>

    {{-- Record salary --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <x-icon name="banknotes" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.record_salary') }}</h3>
            </div>
        </div>
        <form method="POST" action="{{ route('staff.salary.store', $employee) }}" class="p-4">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency') }}</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                        <input type="radio" name="currency" value="AFN" @checked($employee->currency === 'AFN') class="sr-only salary-currency-radio">
                        <span>{{ __('messages.afn') }}</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-sm font-bold cursor-pointer transition-all duration-200 active:scale-95 border-ink-200 dark:border-ink-700 text-ink-600 dark:text-ink-300 has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30">
                        <input type="radio" name="currency" value="USD" @checked($employee->currency === 'USD') class="sr-only salary-currency-radio">
                        <span>$</span>
                    </label>
                </div>
                @error('currency') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.amount') }}</label>
                <div class="relative" dir="ltr">
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $employee->monthly_salary) }}" required
                           dir="ltr" inputmode="decimal"
                           class="form-input text-2xl font-extrabold tabular-nums tracking-tight py-4 pe-14">
                    <span id="salary-unit" class="pointer-events-none absolute top-1/2 -translate-y-1/2 end-4 text-sm font-bold text-ink-400 dark:text-ink-500">{{ $employee->currency === 'USD' ? '$' : __('messages.afn') }}</span>
                </div>
                @error('amount') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="form-label">{{ __('messages.for_month') }}</label>
                    <div class="relative" dir="ltr">
                        <input type="date" name="for_month" value="{{ date('Y-m-01') }}" required dir="ltr" class="form-input pe-11">
                        <x-icon name="calendar" class="w-5 h-5 pointer-events-none absolute top-1/2 -translate-y-1/2 end-3.5 text-ink-400 dark:text-ink-500"/>
                    </div>
                    @error('for_month') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.notes') }}</label>
                    <div class="relative">
                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-input ps-10" placeholder="{{ __('messages.notes_optional') }}">
                        <x-icon name="document-text" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    </div>
                    @error('notes') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit" class="btn-primary w-full">
                <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
                {{ __('messages.salary_record') }}
            </button>
        </form>
    </div>

    {{-- Salary history --}}
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-icon name="clock" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.salary_history') }}</h3>
                </div>
                <span class="text-[11px] font-bold tabular-nums text-ink-500 dark:text-ink-400"><bdi>{{ number_format($paidTotal) }}</bdi></span>
            </div>
        </div>
        @forelse ($payments as $payment)
            <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 {{ $payment->currency === 'USD' ? 'bg-primary-500/10 dark:bg-primary-500/15' : 'bg-brand/10 dark:bg-brand/20' }}">
                        <x-icon name="banknotes" class="w-4 h-4 {{ $payment->currency === 'USD' ? 'text-primary-600 dark:text-primary-400' : 'text-brand' }}" strokeWidth="1.8"/>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-800 dark:text-ink-200 tabular-nums"><bdi>{{ local_date($payment->for_month, 'Y/m') }}</bdi></div>
                        @if ($payment->notes)
                            <div class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $payment->notes }}</div>
                        @endif
                    </div>
                </div>
                <span class="text-sm font-extrabold tabular-nums text-secondary-600 dark:text-secondary-400 flex-shrink-0 ms-3">
                    <x-money :amount="$payment->amount" :currency="$payment->currency" symbol-class="text-[10px] font-medium text-secondary-600 dark:text-secondary-400"/>
                </span>
            </div>
        @empty
            <x-empty-state title="{{ __('messages.no_salary_recorded') }}">
                <x-icon name="banknotes" class="w-6 h-6 text-ink-400"/>
            </x-empty-state>
        @endforelse
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const unit = document.getElementById('salary-unit');
        if (!unit) return;
        document.querySelectorAll('.salary-currency-radio').forEach(function (radio) {
            radio.addEventListener('change', function () {
                unit.textContent = radio.value === 'USD' ? '$' : @json(__('messages.afn'));
            });
        });
    })();
</script>
@endpush
