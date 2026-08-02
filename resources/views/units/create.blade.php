@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('units.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_unit') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('units.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-input" placeholder="{{ __('messages.such_as') }}: کیلوګرام, کارتون, ټوټه">
                @error('name') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.short_name_optional') }}</label>
                <input type="text" name="short_name" value="{{ old('short_name') }}" class="form-input" placeholder="{{ __('messages.such_as') }}: کیلو, کار, ټ">
                @error('short_name') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.submit') }}</button>
        </form>
    </div>
@endsection