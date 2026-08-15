@extends('layouts.auth')

@section('title', __('messages.forgot_password_title'))

@section('content')
    <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-1">{{ __('messages.forgot_password_title') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mb-6">{{ __('messages.forgot_password_hint') }}</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-5">
            <label class="form-label">{{ __('messages.phone') }}</label>
            <input type="tel" name="phone" value="{{ old('phone') }}" required autofocus autocomplete="tel" class="form-input" placeholder="+93 XXX XXX XXX">
            @error('phone')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn-primary w-full">
            <x-icon name="envelope" class="w-4 h-4"/>
            {{ __('messages.send_otp') }}
        </button>
    </form>

    <p class="mt-5 text-center text-xs text-ink-500 dark:text-ink-400">
        {{ __('messages.remember_password') }}
        <a href="{{ route('login') }}" class="text-primary-600 dark:text-primary-400 font-semibold">{{ __('messages.login') }}</a>
    </p>
@endsection
