@extends('layouts.auth')

@section('title', __('messages.login'))

@section('content')
    <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-1">{{ __('messages.login') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mb-6">{{ __('messages.login_hint') }}</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-4">
            <label class="form-label">{{ __('messages.email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="form-input" placeholder="you@example.com">
            @error('email')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <div class="flex items-center justify-between">
                <label class="form-label">{{ __('messages.password') }}</label>
                <a href="{{ route('password.request') }}" class="text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors">
                    {{ __('messages.forgot_password') }}
                </a>
            </div>
            <div class="relative">
                <input type="password" name="password" required autocomplete="current-password" class="form-input pe-11" placeholder="••••••••">
                <button type="button" data-password-toggle="input[name='password']" tabindex="-1" aria-label="{{ __('messages.show_password') }}"
                    class="absolute inset-y-0 end-0 flex items-center pe-3.5 text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 transition-colors">
                    <x-icon name="eye" class="w-4 h-4 icon-show"/>
                    <x-icon name="eye-slash" class="w-4 h-4 icon-hide hidden"/>
                </button>
            </div>
            @error('password')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-center gap-2 text-xs text-ink-600 dark:text-ink-400 mb-5">
            <input type="checkbox" name="remember" class="rounded border-ink-300 text-primary-600 focus:ring-primary-500">
            <span>{{ __('messages.remember_me') }}</span>
        </label>

        <button type="submit" class="btn-primary w-full">
            <x-icon name="arrow-right-on-rectangle" class="w-4 h-4"/>
            {{ __('messages.login') }}
        </button>
    </form>

    <div class="mt-5 flex items-center gap-3">
        <div class="flex-1 h-px bg-ink-100 dark:bg-white/[0.06]"></div>
        <span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">{{ __('messages.or') }}</span>
        <div class="flex-1 h-px bg-ink-100 dark:bg-white/[0.06]"></div>
    </div>

    <a href="{{ route('login.phone') }}" class="mt-5 w-full inline-flex items-center justify-center gap-2 btn-secondary">
        <x-icon name="device-phone-mobile" class="w-4 h-4"/>
        {{ __('messages.phone_login') }}
    </a>

    <p class="mt-5 text-center text-xs text-ink-500 dark:text-ink-400">
        {{ __('messages.no_account') }}
        <a href="{{ route('register') }}" class="text-primary-600 dark:text-primary-400 font-semibold">{{ __('messages.register') }}</a>
    </p>
@endsection
