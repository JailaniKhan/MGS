@extends('layouts.auth')

@section('title', __('messages.forgot_password_title'))

@section('content')
    <h2 class="text-[1.625rem] font-extrabold tracking-tight text-ink-900 dark:text-white leading-tight">{{ __('messages.forgot_password_title') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mt-1 mb-7">{{ __('messages.forgot_password_hint') }}</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-5">
            <label for="forgot-phone" class="form-label">{{ __('messages.phone') }}</label>
            <div class="relative" dir="ltr">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="device-phone-mobile" class="w-4 h-4"/>
                </span>
                <input id="forgot-phone" type="tel" name="phone" value="{{ old('phone') }}" required autofocus autocomplete="tel"
                       class="form-input ps-11" placeholder="+93 XXX XXX XXX" dir="ltr">
            </div>
            @error('phone')<p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn-primary w-full py-4">
            <x-icon name="envelope" class="w-4 h-4"/>
            {{ __('messages.send_otp') }}
        </button>
    </form>

    <p class="mt-7 text-center text-xs text-ink-500 dark:text-ink-400">
        {{ __('messages.remember_password') }}
        <a href="{{ route('login') }}" class="text-primary-600 dark:text-primary-400 font-bold hover:text-primary-700 dark:hover:text-primary-300 transition-colors">{{ __('messages.login') }}</a>
    </p>
@endsection
