@php
    use App\Models\Setting;
    $locale = Setting::get('language', app()->getLocale());
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ in_array($locale, ['ps', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MGS - @yield('title', __('messages.login'))</title>
    @fonts
    @if(in_array($locale, ['ps', 'fa']))
        @vite(['resources/css/app-rtl.css', 'resources/js/app.js'])
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
</head>
<body class="bg-ink-50 dark:bg-[#0b0c0e] min-h-screen flex items-center justify-center p-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <!-- Language switch (guests included) -->
        <div class="flex justify-end mb-3">
            <select onchange="changeAuthLanguage(this.value)"
                class="text-[11px] bg-white/80 dark:bg-[#16181c] border border-ink-200 dark:border-ink-700 rounded-xl px-2.5 py-2
                       appearance-none cursor-pointer transition-all duration-200 hover:border-primary-300 dark:hover:border-primary-600
                       focus:outline-none focus:ring-2 focus:ring-primary-500/30 font-semibold text-ink-700 dark:text-ink-300">
                <option value="ps" {{ $locale === 'ps' ? 'selected' : '' }}>{{ __('messages.pashto') }}</option>
                <option value="fa" {{ $locale === 'fa' ? 'selected' : '' }}>{{ __('messages.persian') }}</option>
                <option value="en" {{ $locale === 'en' ? 'selected' : '' }}>{{ __('messages.english') }}</option>
            </select>
        </div>

        <!-- Brand -->
        <div class="text-center mb-6">
            <div class="w-16 h-16 rounded-2xl brand-grad text-white flex items-center justify-center mx-auto shadow-lg shadow-primary-500/30 mb-3">
                <span class="text-white font-bold text-2xl">M</span>
            </div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-white">MGS</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">{{ __('messages.business_mgmt_system') }}</p>
        </div>

        <div class="bg-white dark:bg-[#16181c] rounded-2xl border border-ink-100 dark:border-white/[0.06] p-6 shadow-card">
            @if (session('success'))
                <div class="flex items-center gap-2.5 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800/50 rounded-xl px-3.5 py-2.5 mb-5">
                    <x-icon name="check-circle" class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0" strokeWidth="2"/>
                    <p class="text-xs font-medium text-primary-700 dark:text-primary-300">{{ session('success') }}</p>
                </div>
            @endif
            @yield('content')
        </div>

        <p class="text-center mt-5 text-[11px] text-ink-400 dark:text-ink-500">{{ __('messages.business_mgmt_system') }}</p>
    </div>

    <script>
        function changeAuthLanguage(lang) {
            fetch('{{ route('language.update') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({ language: lang }),
            })
            .then(() => window.location.reload())
            .catch(() => window.location.reload());
        }

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
