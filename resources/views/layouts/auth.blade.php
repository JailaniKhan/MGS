@php
    use App\Models\Setting;
    $locale = Setting::get('language', app()->getLocale());
    $isRtl = in_array($locale, ['ps', 'fa']);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" data-language-url="{{ route('language.update') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MGS - @yield('title', __('messages.login'))</title>
    <script>
        try {
            const t = localStorage.getItem('mgs-theme');
            const d = t === 'dark' || ((!t || t === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (d) document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
    @fonts
    @if($isRtl)
        @vite(['resources/css/app-rtl.css', 'resources/js/app.js'])
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
</head>
<body class="bg-ink-50 dark:bg-[#0b0c0e] min-h-screen font-sans antialiased text-ink-700 dark:text-ink-200">
    <div class="auth-ambient" aria-hidden="true"></div>

    <div class="relative z-10 min-h-screen flex flex-col p-5 pb-8">
        <!-- Top row: language switch -->
        <div class="flex justify-end">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-ink-400 dark:text-ink-500">
                    <x-icon name="language" class="w-3.5 h-3.5"/>
                </span>
                <select onchange="changeLanguage(this.value)" aria-label="{{ __('messages.language') }}"
                    class="text-[11px] ps-8 pe-2.5 py-2 rounded-full appearance-none cursor-pointer font-semibold
                           bg-white/90 dark:bg-[#16181c] text-ink-700 dark:text-ink-300
                           border border-ink-200 dark:border-ink-700
                           shadow-card backdrop-blur-sm
                           transition-all duration-200
                           hover:border-primary-300 dark:hover:border-primary-600
                           focus:outline-none focus:ring-2 focus:ring-primary-500/30">
                    <option value="ps" {{ $locale === 'ps' ? 'selected' : '' }}>{{ __('messages.pashto') }}</option>
                    <option value="fa" {{ $locale === 'fa' ? 'selected' : '' }}>{{ __('messages.persian') }}</option>
                    <option value="en" {{ $locale === 'en' ? 'selected' : '' }}>{{ __('messages.english') }}</option>
                </select>
            </div>
        </div>

        <!-- Content column -->
        <div class="flex-1 flex flex-col items-center justify-center w-full max-w-sm mx-auto">
            <!-- Brand lockup -->
            <div class="flex items-center gap-3 mb-7">
                <div class="w-12 h-12 rounded-2xl brand-grad text-white flex items-center justify-center shadow-fab">
                    <x-icon name="app-icon" class="w-6 h-6"/>
                </div>
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-ink-900 dark:text-white leading-none">MGS</h1>
                    <p class="text-[11px] font-medium text-ink-500 dark:text-ink-400 mt-1">{{ __('messages.business_mgmt_system') }}</p>
                </div>
            </div>

            <div class="w-full bg-white dark:bg-[#16181c] rounded-[1.5rem] border border-ink-100 dark:border-white/[0.06] p-6 pt-7 shadow-card">
                @if (session('success'))
                    <div class="flex items-center gap-2.5 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800/50 rounded-xl px-3.5 py-2.5 mb-5">
                        <x-icon name="check-circle" class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0" strokeWidth="2"/>
                        <p class="text-xs font-medium text-primary-700 dark:text-primary-300">{{ session('success') }}</p>
                    </div>
                @endif
                @yield('content')
            </div>
        </div>

        <p class="text-center mt-6 text-[11px] text-ink-400 dark:text-ink-500">{{ __('messages.business_mgmt_system') }}</p>
    </div>

    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-password-toggle]');
            if (!btn) return;
            const input = document.querySelector(btn.getAttribute('data-password-toggle'));
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            const showIcon = btn.querySelector('.icon-show');
            const hideIcon = btn.querySelector('.icon-hide');
            if (showIcon) showIcon.classList.toggle('hidden', !show);
            if (hideIcon) hideIcon.classList.toggle('hidden', show);
        });
    </script>

    @stack('scripts')
</body>
</html>
