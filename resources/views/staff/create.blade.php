@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_staff') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form method="POST" action="{{ route('staff.store') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-input">
                @error('name')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="form-input">
                @error('phone')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.position') }}</label>
                <input type="text" name="position" value="{{ old('position') }}" class="form-input">
                @error('position')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.monthly_salary') }}</label>
                <input type="number" step="0.01" name="monthly_salary" value="{{ old('monthly_salary') }}" required class="form-input">
                @error('monthly_salary')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN">{{ __('messages.afn') }}</option>
                    <option value="USD">$</option>
                </select>
            </div>
            <button type="submit" class="btn-primary w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>{{ __('messages.register') }}</button>
        </form>
    </div>
@endsection