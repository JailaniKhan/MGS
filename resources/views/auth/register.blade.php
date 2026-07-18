@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto page-enter">
    <div class="text-center mb-6">
        <div class="w-16 h-16 rounded-2xl bg-brand text-white flex items-center justify-center mx-auto shadow-lg mb-3">
            <span class="text-white font-bold text-xl">M</span>
        </div>
        <h2 class="text-xl font-bold text-ink-900 dark:text-white">{{ __('messages.register') }}</h2>
    </div>

    <div class="card p-4">
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-input">
                @error('name')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="form-input">
                @error('email')<p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.password') }}</label>
                <input type="password" name="password" required class="form-input">
            </div>
            <div class="mb-4">
                <label class="form-label">{{ __('messages.password_confirmation') }}</label>
                <input type="password" name="password_confirmation" required class="form-input">
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('messages.register') }}</button>
        </form>
    </div>

    <p class="text-center mt-4 text-xs text-ink-500 dark:text-ink-400">
        {{ __('messages.have_account') }}
        <a href="{{ route('login') }}" class="text-primary-600 dark:text-primary-400 font-semibold">{{ __('messages.login') }}</a>
    </p>
</div>
@endsection