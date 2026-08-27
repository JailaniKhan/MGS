@extends('layouts.app')

@section('content')
<div class="page-enter">
    <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">{{ __('messages.app_lock') }}</h2>

    <!-- PIN Lock -->
    <div class="card p-4 mb-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                    <x-icon name="lock-closed" class="w-5 h-5 text-primary-600 dark:text-primary-400"/>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.pin_lock') }}</h3>
                    <p class="text-[11px] text-ink-500 dark:text-ink-400">{{ __('messages.pin_lock_description') }}</p>
                </div>
            </div>
            @if($pinEnabled === '1')
                <span class="badge badge-success">{{ __('messages.enabled') }}</span>
            @else
                <span class="badge bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-400">{{ __('messages.disabled') }}</span>
            @endif
        </div>

        @if($pinEnabled === '1')
            <form action="{{ route('app-lock.remove-pin') }}" method="POST" class="space-y-3">
                @csrf
                @method('DELETE')
                <div>
                    <label class="form-label">{{ __('messages.enter_pin') }}</label>
                    <input type="password" name="pin" maxlength="4" pattern="[0-9]{4}" inputmode="numeric"
                           class="form-input text-center text-2xl tracking-[0.5em]" placeholder="••••" required>
                </div>
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
                    <x-icon name="finger-print" class="w-5 h-5 text-secondary-600 dark:text-secondary-400"/>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white">{{ __('messages.fingerprint_lock') }}</h3>
                    <p class="text-[11px] text-ink-500 dark:text-ink-400">{{ __('messages.fingerprint_lock_description') }}</p>
                </div>
            </div>
            <form action="{{ route('app-lock.toggle-biometric') }}" method="POST">
                @csrf
                <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors
                    {{ $biometricEnabled === '1' ? 'bg-primary-500' : 'bg-ink-200 dark:bg-ink-700' }}">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform
                        {{ $biometricEnabled === '1' ? 'translate-x-6' : 'translate-x-1' }}"/>
                </button>
            </form>
        </div>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>
            {{ __('messages.back') }}
        </a>
    </div>
</div>
@endsection
