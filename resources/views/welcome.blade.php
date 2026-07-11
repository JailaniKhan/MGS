<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(in_array(app()->getLocale(), ['ps', 'fa'])) dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'MGS') }}</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md text-center">
        <div class="w-20 h-20 rounded-2xl bg-brand text-white flex items-center justify-center mx-auto shadow-xl mb-6">
            <span class="text-white font-extrabold text-3xl">M</span>
        </div>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white mb-2">{{ config('app.name', 'MGS') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">{{ __('messages.business_mgmt_system') }}</p>
        <div class="space-y-3">
            @auth
                <a href="{{ url('/dashboard') }}" class="btn-primary w-full block text-center">{{ __('messages.dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="btn-primary w-full block text-center">{{ __('messages.login') }}</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="block w-full py-3 rounded-xl text-sm font-bold text-primary-600 dark:text-primary-400 border-2 border-primary-500/30 dark:border-primary-400/30 text-center transition-all duration-200 hover:bg-primary-50 dark:hover:bg-primary-900/20">{{ __('messages.register') }}</a>
                @endif
            @endauth
        </div>
        <p class="mt-8 text-[11px] text-gray-400 dark:text-gray-500">{{ __('messages.built_by') }} &mdash; v{{ app()->version() }}</p>
    </div>
</body>
</html>