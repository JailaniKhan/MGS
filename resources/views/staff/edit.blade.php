@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.edit_staff') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form method="POST" action="{{ route('staff.update', $employee) }}">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="form-label">{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ $employee->name }}" required class="form-input">
                @error('name')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.phone') }}</label>
                <input type="text" name="phone" value="{{ $employee->phone }}" class="form-input">
                @error('phone')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.position') }}</label>
                <input type="text" name="position" value="{{ $employee->position }}" class="form-input">
                @error('position')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.monthly_salary') }}</label>
                <input type="number" step="0.01" name="monthly_salary" value="{{ $employee->monthly_salary }}" required class="form-input">
                @error('monthly_salary')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN" @selected($employee->currency === 'AFN')>{{ __('messages.afn') }}</option>
                    <option value="USD" @selected($employee->currency === 'USD')>$</option>
                </select>
            </div>
            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.save') }}</button>
        </form>
    </div>
@endsection