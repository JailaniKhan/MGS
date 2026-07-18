@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('units.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
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
            <button type="submit" class="btn-primary w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ __('messages.submit') }}</button>
        </form>
    </div>
@endsection