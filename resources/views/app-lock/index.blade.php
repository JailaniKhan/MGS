@extends('layouts.app')

@section('content')
<div class="page-enter">
    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ __('messages.app_lock') }}</h2>

    <!-- PIN Lock -->
    <div class="card p-4 mb-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ __('messages.pin_lock') }}</h3>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('messages.pin_lock_description') }}</p>
                </div>
            </div>
            @if($pinEnabled === '1')
                <span class="badge badge-success">{{ __('messages.enabled') }}</span>
            @else
                <span class="badge bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">{{ __('messages.disabled') }}</span>
            @endif
        </div>

        @if($pinEnabled === '1')
            <form action="{{ route('app-lock.remove-pin') }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger w-full">{{ __('messages.remove_pin') }}</button>
            </form>
        @else
            <form action="{{ route('app-lock.set-pin') }}" method="POST" id="pin-form">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="form-label">{{ __('messages.enter_pin') }}</label>
                        <input type="password" name="pin" maxlength="4" pattern="[0-9]{4}" inputmode="numeric"
                               class="form-input text-center text-2xl tracking-[0.5em]" placeholder="••••" required>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.confirm_pin') }}</label>
                        <input type="password" name="pin_confirmation" maxlength="4" pattern="[0-9]{4}" inputmode="numeric"
                               class="form-input text-center text-2xl tracking-[0.5em]" placeholder="••••" required>
                    </div>
                    <button type="submit" class="btn-primary w-full">{{ __('messages.set_pin') }}</button>
                </div>
            </form>
        @endif
    </div>

    <!-- Biometric Lock -->
    <div class="card p-4 mb-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-secondary-100 dark:bg-secondary-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0119.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 004.5 10.5a48.667 48.667 0 00-1.418 8.773 7.46 7.46 0 01-1.418-8.773c0-1.57.564-3.043 1.418-4.243M12.75 10.5a3 3 0 11-6 0 3 3 0 016 0zm0 0v1.5a3 3 0 01-3 3m6-3v.75"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ __('messages.fingerprint_lock') }}</h3>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('messages.fingerprint_lock_description') }}</p>
                </div>
            </div>
            <form action="{{ route('app-lock.toggle-biometric') }}" method="POST">
                @csrf
                <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors
                    {{ $biometricEnabled === '1' ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-700' }}">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform
                        {{ $biometricEnabled === '1' ? 'translate-x-6' : 'translate-x-1' }}"/>
                </button>
            </form>
        </div>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            {{ __('messages.back') }}
        </a>
    </div>
</div>
@endsection
