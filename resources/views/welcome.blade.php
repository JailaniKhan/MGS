<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(in_array(app()->getLocale(), ['ps', 'fa'])) dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MGS') }}</title>
    <script>
        try {
            const t = localStorage.getItem('mgs-theme');
            const d = t === 'dark' || ((!t || t === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (d) document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @if(in_array(app()->getLocale(), ['ps', 'fa']))
            @vite(['resources/css/app-rtl.css', 'resources/js/app.js'])
        @else
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    @endif
</head>
<body class="bg-ink-50 dark:bg-[#0b0c0e] min-h-screen font-sans antialiased text-ink-700 dark:text-ink-200">
    <div class="auth-ambient" aria-hidden="true"></div>

    <div class="relative z-10 min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-sm text-center">
            <div class="w-20 h-20 rounded-3xl brand-grad text-white flex items-center justify-center mx-auto shadow-fab mb-6">
                <x-icon name="app-icon" class="w-10 h-10"/>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white mb-2">{{ config('app.name', 'MGS') }}</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mb-9">{{ __('messages.business_mgmt_system') }}</p>

            <div class="space-y-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn-primary w-full block py-4">{{ __('messages.dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary w-full block py-4">{{ __('messages.login') }}</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-[0.875rem] text-sm font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800/40 transition-all duration-200 active:scale-[0.98] hover:bg-primary-100 dark:hover:bg-primary-900/30">
                            <x-icon name="user-plus" class="w-4 h-4"/>
                            {{ __('messages.register') }}
                        </a>
                    @endif
                @endauth
            </div>

            <p class="mt-10 text-[11px] text-ink-400 dark:text-ink-500">{{ __('messages.built_by }} &mdash; v{{ app()->version() }}</p>
        </div>
    </div>
</body>
</html>
