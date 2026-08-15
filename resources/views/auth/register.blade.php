@extends('layouts.auth')

@section('title', __('messages.register'))

@section('content')
    <h2 class="text-[1.625rem] font-extrabold tracking-tight text-ink-900 dark:text-white leading-tight">{{ __('messages.register') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mt-1 mb-7">{{ __('messages.register_hint') }}</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <div>
            <label for="reg-name" class="form-label">{{ __('messages.name') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="user" class="w-4 h-4"/>
                </span>
                <input id="reg-name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name"
                       class="form-input ps-11" placeholder="{{ __('messages.name') }}">
            </div>
            @error('name')<p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="reg-email" class="form-label">{{ __('messages.email') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="at-symbol" class="w-4 h-4"/>
                </span>
                <input id="reg-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                       class="form-input ps-11" placeholder="you@example.com" dir="ltr">
            </div>
            @error('email')<p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="reg-phone" class="form-label">{{ __('messages.phone') }}
                <span class="text-[10px] font-semibold uppercase tracking-wide text-ink-400 normal-case">· {{ __('messages.optional') }}</span>
            </label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="device-phone-mobile" class="w-4 h-4"/>
                </span>
                <input id="reg-phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel"
                       class="form-input ps-11" placeholder="+93 XXX XXX XXX" dir="ltr">
            </div>
            <p class="text-[11px] text-ink-400 dark:text-ink-500 mt-1.5">{{ __('messages.phone_help') }}</p>
            @error('phone')<p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="reg-password" class="form-label">{{ __('messages.password') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="key" class="w-4 h-4"/>
                </span>
                <input id="reg-password" type="password" name="password" required minlength="6" autocomplete="new-password"
                       class="form-input ps-11 pe-12" placeholder="••••••••" dir="ltr">
                <button type="button" data-password-toggle="#reg-password" tabindex="-1" aria-label="{{ __('messages.show_password') }}"
                    class="absolute inset-y-0 end-0 flex items-center pe-4 text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 transition-colors">
                    <x-icon name="eye" class="w-4 h-4 icon-show"/>
                    <x-icon name="eye-slash" class="w-4 h-4 icon-hide hidden"/>
                </button>
            </div>
            <p class="text-[11px] text-ink-400 dark:text-ink-500 mt-1.5">{{ __('messages.password_help') }}</p>
            @error('password')<p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="reg-password-confirm" class="form-label">{{ __('messages.password_confirmation') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-ink-400 dark:text-ink-500">
                    <x-icon name="shield-check" class="w-4 h-4"/>
                </span>
                <input id="reg-password-confirm" type="password" name="password_confirmation" required minlength="6" autocomplete="new-password"
                       class="form-input ps-11" placeholder="••••••••" dir="ltr">
            </div>
        </div>

        <button type="submit" class="btn-primary w-full py-4">
            <x-icon name="user-plus" class="w-4 h-4"/>
            {{ __('messages.register') }}
        </button>
    </form>

    <p class="mt-7 text-center text-xs text-ink-500 dark:text-ink-400">
        {{ __('messages.have_account') }}
        <a href="{{ route('login') }}" class="text-primary-600 dark:text-primary-400 font-bold hover:text-primary-700 dark:hover:text-primary-300 transition-colors">{{ __('messages.login') }}</a>
    </p>
@endsection
