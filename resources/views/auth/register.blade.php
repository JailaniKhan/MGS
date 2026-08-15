@extends('layouts.auth')

@section('title', __('messages.register'))

@section('content')
    <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-1">{{ __('messages.register') }}</h2>
    <p class="text-sm text-ink-500 dark:text-ink-400 mb-6">{{ __('messages.register_hint') }}</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="mb-4">
            <label class="form-label">{{ __('messages.name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}" required autocomplete="name" class="form-input" placeholder="{{ __('messages.name') }}">
            @error('name')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label">{{ __('messages.email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="form-input" placeholder="you@example.com">
            @error('email')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <div class="flex items-center justify-between">
                <label class="form-label">{{ __('messages.phone') }}</label>
                <span class="text-[10px] font-semibold uppercase tracking-wide text-ink-400">{{ __('messages.optional') }}</span>
            </div>
            <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="form-input" placeholder="+93 XXX XXX XXX">
            <p class="text-[11px] text-ink-400 dark:text-ink-500 mt-1">{{ __('messages.phone_help') }}</p>
            @error('phone')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label">{{ __('messages.password') }}</label>
            <div class="relative">
                <input type="password" name="password" required minlength="6" autocomplete="new-password" class="form-input pe-11" placeholder="••••••••">
                <button type="button" data-password-toggle="input[name='password']" tabindex="-1" aria-label="{{ __('messages.show_password') }}"
                    class="absolute inset-y-0 end-0 flex items-center pe-3.5 text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 transition-colors">
                    <x-icon name="eye" class="w-4 h-4 icon-show"/>
                    <x-icon name="eye-slash" class="w-4 h-4 icon-hide hidden"/>
                </button>
            </div>
            <p class="text-[11px] text-ink-400 dark:text-ink-500 mt-1">{{ __('messages.password_help') }}</p>
            @error('password')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-5">
            <label class="form-label">{{ __('messages.password_confirmation') }}</label>
            <input type="password" name="password_confirmation" required minlength="6" autocomplete="new-password" class="form-input" placeholder="••••••••">
        </div>

        <button type="submit" class="btn-primary w-full">
            <x-icon name="user-plus" class="w-4 h-4"/>
            {{ __('messages.register') }}
        </button>
    </form>

    <p class="mt-5 text-center text-xs text-ink-500 dark:text-ink-400">
        {{ __('messages.have_account') }}
        <a href="{{ route('login') }}" class="text-primary-600 dark:text-primary-400 font-semibold">{{ __('messages.login') }}</a>
    </p>
@endsection
