@extends('layouts.auth')

@section('title', __('messages.login'))

@section('content')
    <h2 class="text-[1.625rem] font-extrabold tracking-tight text-ink-900 dark:text-white leading-tight">{{ __('messages.login') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mt-1 mb-7">{{ __('messages.login_hint') }}</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div>
            <label for="login-email" class="form-label">{{ __('messages.email') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="at-symbol" class="w-4 h-4"/>
                </span>
                <input id="login-email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                       class="form-input ps-11" placeholder="you@example.com" dir="ltr">
            </div>
            @error('email')<p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="login-password" class="form-label">{{ __('messages.password') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="key" class="w-4 h-4"/>
                </span>
                <input id="login-password" type="password" name="password" required autocomplete="current-password"
                       class="form-input ps-11 pe-12" placeholder="••••••••" dir="ltr">
                <button type="button" data-password-toggle="#login-password" tabindex="-1" aria-label="{{ __('messages.show_password') }}"
                    class="absolute inset-y-0 end-0 flex items-center pe-4 text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 transition-colors">
                    <x-icon name="eye" class="w-4 h-4 icon-show"/>
                    <x-icon name="eye-slash" class="w-4 h-4 icon-hide hidden"/>
                </button>
            </div>
            @error('password')<p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p>@enderror
            <div class="flex justify-end mt-1.5">
                <a href="{{ route('password.request') }}" class="text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors">
                    {{ __('messages.forgot_password') }}
                </a>
            </div>
        </div>

        <label class="flex items-center gap-2.5 text-xs font-medium text-ink-600 dark:text-ink-400 cursor-pointer select-none">
            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-ink-300 dark:border-ink-600 text-primary-600 focus:ring-primary-500 dark:bg-white/[0.04]">
            <span>{{ __('messages.remember_me') }}</span>
        </label>

        <button type="submit" class="btn-primary w-full py-4">
            <x-icon name="arrow-right-on-rectangle" class="w-4 h-4"/>
            {{ __('messages.login') }}
        </button>
    </form>

    <div class="mt-6 flex items-center gap-3">
        <div class="flex-1 h-px bg-ink-100 dark:bg-white/[0.06]"></div>
        <span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">{{ __('messages.or') }}</span>
        <div class="flex-1 h-px bg-ink-100 dark:bg-white/[0.06]"></div>
    </div>

    <a href="{{ route('login.phone') }}" class="mt-5 w-full inline-flex btn-secondary py-3.5">
        <x-icon name="device-phone-mobile" class="w-4 h-4"/>
        {{ __('messages.phone_login') }}
    </a>

    <p class="mt-7 text-center text-xs text-ink-500 dark:text-ink-400">
        {{ __('messages.no_account') }}
        <a href="{{ route('register') }}" class="text-primary-600 dark:text-primary-400 font-bold hover:text-primary-700 dark:hover:text-primary-300 transition-colors">{{ __('messages.register') }}</a>
    </p>
@endsection
