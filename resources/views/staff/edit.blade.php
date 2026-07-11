@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-2">{{ __('messages.edit_staff') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form method="POST" action="{{ route('staff.update', $employee) }}">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="form-label">{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ $employee->name }}" required class="form-input">
                @error('name')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.phone') }}</label>
                <input type="text" name="phone" value="{{ $employee->phone }}" class="form-input">
                @error('phone')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.position') }}</label>
                <input type="text" name="position" value="{{ $employee->position }}" class="form-input">
                @error('position')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.monthly_salary') }}</label>
                <input type="number" step="0.01" name="monthly_salary" value="{{ $employee->monthly_salary }}" required class="form-input">
                @error('monthly_salary')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN" @selected($employee->currency === 'AFN')>{{ __('messages.afn') }}</option>
                    <option value="USD" @selected($employee->currency === 'USD')>$</option>
                </select>
            </div>
            <button type="submit" class="btn-primary w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ __('messages.save') }}</button>
        </form>
    </div>
@endsection