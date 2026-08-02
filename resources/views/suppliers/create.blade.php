@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('suppliers.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_supplier') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('suppliers.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-input">
                @error('name') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="form-input">
                @error('phone') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.address') }}</label>
                <textarea name="address" rows="2" class="form-input">{{ old('address') }}</textarea>
                @error('address') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.submit') }}</button>
        </form>
    </div>
@endsection