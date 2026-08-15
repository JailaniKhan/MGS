@extends('layouts.auth')

@section('title', __('messages.reset_password_title'))

@section('content')
    <h2 class="text-[1.625rem] font-extrabold tracking-tight text-ink-900 dark:text-white leading-tight">{{ __('messages.reset_password_title') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mt-1 mb-7">{{ __('messages.reset_password_hint') }}</p>

    @php
        $phone = old('phone', request()->query('phone', ''));
    @endphp

    <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800/50 rounded-xl px-3.5 py-2.5 mb-6 flex items-center gap-2.5">
        <x-icon name="device-phone-mobile" class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0"/>
        <span class="text-xs font-bold text-primary-700 dark:text-primary-300" dir="ltr">{{ $phone ?: __('messages.phone') }}</span>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone }}">
        <div>
            <label for="reset-otp" class="form-label">{{ __('messages.otp_code') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="shield-check" class="w-4 h-4"/>
                </span>
                <input id="reset-otp" type="text" name="otp" value="{{ old('otp') }}" required maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                       class="form-input ps-11 text-center text-xl tracking-[0.35em] font-bold" placeholder="000000" dir="ltr">
            </div>
            @error('otp')<p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="reset-password" class="form-label">{{ __('messages.new_password') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="key" class="w-4 h-4"/>
                </span>
                <input id="reset-password" type="password" name="password" required minlength="6" autocomplete="new-password"
                       class="form-input ps-11 pe-12" placeholder="••••••••" dir="ltr">
                <button type="button" data-password-toggle="#reset-password" tabindex="-1" aria-label="{{ __('messages.show_password') }}"
                    class="absolute inset-y-0 end-0 flex items-center pe-4 text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 transition-colors">
                    <x-icon name="eye" class="w-4 h-4 icon-show"/>
                    <x-icon name="eye-slash" class="w-4 h-4 icon-hide hidden"/>
                </button>
            </div>
            @error('password')<p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="reset-password-confirm" class="form-label">{{ __('messages.password_confirmation') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="shield-check" class="w-4 h-4"/>
                </span>
                <input id="reset-password-confirm" type="password" name="password_confirmation" required minlength="6" autocomplete="new-password"
                       class="form-input ps-11" placeholder="••••••••" dir="ltr">
            </div>
        </div>

        <button type="submit" class="btn-primary w-full py-4">
            <x-icon name="key" class="w-4 h-4"/>
            {{ __('messages.reset_password') }}
        </button>
    </form>

    <p class="mt-7 text-center text-xs">
        <a href="{{ route('password.request') }}" class="text-primary-600 dark:text-primary-400 font-bold hover:text-primary-700 dark:hover:text-primary-300 transition-colors">{{ __('messages.resend_otp') }}</a>
    </p>
@endsection
