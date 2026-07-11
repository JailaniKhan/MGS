@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
            {{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-2">{{ __('messages.new_customer') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('customers.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="form-label">{{ __('messages.name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="form-input" placeholder="{{ __('messages.name') }}">
                    @error('name') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="form-input" placeholder="{{ __('messages.phone') }}">
                    @error('phone') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('messages.address') }}</label>
                    <textarea name="address" rows="2" class="form-input" placeholder="{{ __('messages.address') }}">{{ old('address') }}</textarea>
                    @error('address') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="pt-1">
                    <button type="submit" class="btn-primary w-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('messages.save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection