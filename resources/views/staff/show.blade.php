@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-brand text-white flex items-center justify-center shadow-sm flex-shrink-0">
                <x-icon name="user" class="w-5 h-5 text-white"/>
            </div>
            <div class="flex-1">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ $employee->name }}</h2>
                <p class="text-xs text-ink-500 dark:text-ink-400">{{ $employee->position }}</p>
                <p class="text-sm font-semibold text-primary-600 dark:text-primary-400 mt-1">{{ number_format((float) $employee->monthly_salary, 2) }} {{ $employee->currency }}</p>
            </div>
        </div>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
            <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.record_salary') }}</h3>
        </div>
        <form method="POST" action="{{ route('staff.salary.store', $employee) }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.amount') }}</label>
                <input type="number" step="0.01" name="amount" value="{{ $employee->monthly_salary }}" required class="form-input">
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN" @selected($employee->currency === 'AFN')>{{ __('messages.afn') }}</option>
                    <option value="USD" @selected($employee->currency === 'USD')>$</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.for_month') }}</label>
                <input type="date" name="for_month" value="{{ date('Y-m-01') }}" required class="form-input">
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.notes') }}</label>
                <input type="text" name="notes" class="form-input">
            </div>
            <button type="submit" class="btn-primary w-full"><x-icon name="plus" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.salary_record') }}</button>
        </form>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.15s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.salary_history') }}</h3>
            </div>
        </div>
        @forelse ($employee->salaryPayments as $payment)
            <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                <span class="text-sm text-ink-700 dark:text-ink-300">{{ $payment->for_month->format('Y/m') }}</span>
                <span class="text-sm font-bold text-primary-600 dark:text-primary-400">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</span>
            </div>
        @empty
            <div class="empty-state">
                <p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_salary_recorded') }}</p>
            </div>
        @endforelse
    </div>
@endsection