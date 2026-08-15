@extends('layouts.auth')

@section('title', __('messages.reset_password_title'))

@section('content')
    <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-1">{{ __('messages.reset_password_title') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mb-6">{{ __('messages.reset_password_hint') }}</p>

    @php
        $phone = old('phone', request()->query('phone', ''));
    @endphp

    <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800/50 rounded-xl px-3.5 py-2.5 mb-5 flex items-center gap-2">
        <x-icon name="device-phone-mobile" class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0"/>
        <span class="text-xs font-semibold text-primary-700 dark:text-primary-300">{{ $phone ?: __('messages.phone') }}</span>
    </div>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone }}">
        <div class="mb-4">
            <label class="form-label">{{ __('messages.otp_code') }}</label>
            <input type="text" name="otp" value="{{ old('otp') }}" required maxlength="6" inputmode="numeric" autocomplete="one-time-code" class="form-input text-center text-2xl tracking-widest" placeholder="000000">
            @error('otp')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label">{{ __('messages.new_password') }}</label>
            <div class="relative">
                <input type="password" name="password" required minlength="6" autocomplete="new-password" class="form-input pe-11" placeholder="••••••••">
                <button type="button" data-password-toggle="input[name='password']" tabindex="-1" aria-label="{{ __('messages.show_password') }}"
                    class="absolute inset-y-0 end-0 flex items-center pe-3.5 text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 transition-colors">
                    <x-icon name="eye" class="w-4 h-4 icon-show"/>
                    <x-icon name="eye-slash" class="w-4 h-4 icon-hide hidden"/>
                </button>
            </div>
            @error('password')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-5">
            <label class="form-label">{{ __('messages.password_confirmation') }}</label>
            <input type="password" name="password_confirmation" required minlength="6" autocomplete="new-password" class="form-input" placeholder="••••••••">
        </div>

        <button type="submit" class="btn-primary w-full">
            <x-icon name="key" class="w-4 h-4"/>
            {{ __('messages.reset_password') }}
        </button>
    </form>

    <p class="mt-5 text-center text-xs text-ink-500 dark:text-ink-400">
        <a href="{{ route('password.request') }}" class="text-primary-600 dark:text-primary-400 font-semibold">{{ __('messages.resend_otp') }}</a>
    </p>
@endsection
