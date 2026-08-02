@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto page-enter">
    <div class="text-center mb-6">
        <div class="w-16 h-16 rounded-2xl bg-brand text-white flex items-center justify-center mx-auto shadow-lg mb-3">
            <span class="text-white font-bold text-xl">M</span>
        </div>
        <h2 class="text-xl font-bold text-ink-900 dark:text-white">{{ __('messages.login') }}</h2>
    </div>

    <div class="card p-4">
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="form-input">
                @error('email')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.password') }}</label>
                <input type="password" name="password" required class="form-input">
            </div>
            <label class="flex items-center gap-2 text-xs text-ink-600 dark:text-ink-400 mb-4">
                <input type="checkbox" name="remember" class="rounded border-ink-300 text-primary-600 focus:ring-primary-500">
                <span>{{ __('messages.remember_me') }}</span>
            </label>
            <button type="submit" class="btn-primary w-full">{{ __('messages.login') }}</button>
        </form>

        <div class="mt-4 text-center border-t border-ink-100 dark:border-ink-700/30 pt-4">
            <a href="{{ route('login.phone') }}" class="inline-flex items-center gap-2 text-xs font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors">
                <x-icon name="device-phone-mobile" class="w-4 h-4"/>
                {{ __('messages.phone_login') }}
            </a>
        </div>
    </div>

    <p class="text-center mt-4 text-xs text-ink-500 dark:text-ink-400">
        {{ __('messages.no_account') }}
        <a href="{{ route('register') }}" class="text-primary-600 dark:text-primary-400 font-semibold">{{ __('messages.register') }}</a>
    </p>
</div>
@endsection