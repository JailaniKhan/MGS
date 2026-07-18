@php
use App\Models\Setting;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ps', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MGS - {{ __('messages.dashboard') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/favicon.svg">
</head>
<body class="bg-ink-50 dark:bg-[#0b0c0e] text-ink-700 dark:text-ink-200 font-sans antialiased">
    <div class="min-h-screen flex flex-col pb-[72px]">

        <!-- HEADER -->
        <header class="bg-white/90 dark:bg-[#16181c]/90 backdrop-blur-xl border-b border-ink-100 dark:border-white/[0.06] sticky top-0 z-40">
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-brand text-white flex items-center justify-center shadow-sm">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 5.5A1.5 1.5 0 015.5 4h13A1.5 1.5 0 0120 5.5v13A1.5 1.5 0 0118.5 20h-13A1.5 1.5 0 014 18.5v-13z"/>
                <path d="M4 9.5h16"/>
                <path d="M8 4v16"/>
            </svg>
        </div>
                    <div>
                        <h1 class="text-sm font-bold text-ink-900 dark:text-white">MGS</h1>
                        <span class="text-[10px] text-ink-500 dark:text-ink-400">{{ $headerDescription ?? __('messages.dashboard') }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative">
                        <form method="POST" action="{{ route('language.update') }}" id="language-form" class="hidden">
                            @csrf
                        </form>
                        <select onchange="changeLanguage(this.value)"
                            class="text-[11px] bg-ink-100/80 dark:bg-ink-800/80 border border-ink-200 dark:border-ink-700 rounded-xl px-2.5 py-2
                                   appearance-none cursor-pointer transition-all duration-200 hover:border-primary-300 dark:hover:border-primary-600
                                   focus:outline-none focus:ring-2 focus:ring-primary-500/30 font-semibold text-ink-700 dark:text-ink-300">
                            <option value="ps" {{ app()->getLocale() === 'ps' ? 'selected' : '' }}>{{ __('messages.pashto') }}</option>
                            <option value="fa" {{ app()->getLocale() === 'fa' ? 'selected' : '' }}>{{ __('messages.persian') }}</option>
                            <option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>{{ __('messages.english') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </header>

        <script>
            function changeLanguage(lang) {
                const form = document.getElementById('language-form');
                const langInput = document.createElement('input');
                langInput.type = 'hidden';
                langInput.name = 'language';
                langInput.value = lang;
                form.appendChild(langInput);
                form.submit();
            }
        </script>

        <!-- FLASH MESSAGES -->
        @if (session('success'))
            <div class="toast" role="alert">
                <div class="flex items-center gap-2.5 bg-white dark:bg-[#1e2127] border border-primary-200 dark:border-primary-800/50 rounded-2xl px-4 py-3 shadow-card">
                    <div class="w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-ink-800 dark:text-ink-200 flex-1">{{ session('success') }}</p>
                    <button onclick="this.closest('.toast').remove()" class="text-ink-400 hover:text-ink-600 dark:hover:text-ink-300 transition-colors p-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif
        @if (session('error'))
            <div class="toast" role="alert">
                <div class="flex items-center gap-2.5 bg-white dark:bg-[#1e2127] border border-danger-200 dark:border-danger-800/50 rounded-2xl px-4 py-3 shadow-card">
                    <div class="w-7 h-7 rounded-full bg-danger-100 dark:bg-danger-900/50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-danger-600 dark:text-danger-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-ink-800 dark:text-ink-200 flex-1">{{ session('error') }}</p>
                    <button onclick="this.closest('.toast').remove()" class="text-ink-400 hover:text-ink-600 dark:hover:text-ink-300 transition-colors p-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        <!-- MAIN CONTENT -->
        <main class="flex-1 px-4 pt-4 page-enter">
            @yield('content')
        </main>
    </div>

    <!-- BOTTOM NAVIGATION -->
    <nav class="fixed bottom-0 left-0 right-0 z-50 px-2 pb-1 pt-0">
        <div class="bg-white/90 dark:bg-[#16181c]/90 backdrop-blur-2xl border border-ink-100 dark:border-white/[0.06] rounded-2xl shadow-nav dark:shadow-nav-dark">
            <div class="relative flex items-center justify-around py-1">
            <span id="nav-pill" class="nav-pill"></span>
                <!-- Dashboard -->
                <a href="{{ route('dashboard') }}"
                   class="nav-item {{ request()->routeIs('dashboard') ? 'nav-item-active' : '' }} group"
                   aria-label="{{ __('messages.dashboard') }}">
                    <svg class="w-5 h-5 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
                    </svg>
                    <span>{{ __('messages.dashboard') }}</span>
                </a>

                <!-- Orders -->
                <a href="{{ route('orders.index') }}"
                   class="nav-item {{ request()->routeIs('orders.*') ? 'nav-item-active' : '' }} group"
                   aria-label="{{ __('messages.orders') }}">
                    <svg class="w-5 h-5 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
                    </svg>
                    <span>{{ __('messages.orders') }}</span>
                </a>

                <!-- Cashbook (center, prominent) -->
                <a href="{{ route('cashbook.index') }}"
                   class="nav-item {{ request()->routeIs('cashbook.*') ? 'nav-item-active' : '' }} group -mt-2"
                   aria-label="{{ __('messages.cashbook') }}">
                    <div class="w-10 h-10 rounded-full brand-grad text-white flex items-center justify-center shadow-fab">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-primary-600 dark:text-primary-400 font-bold">{{ __('messages.cashbook') }}</span>
                </a>

                <!-- Inventory -->
                <a href="{{ route('inventory.index') }}"
                   class="nav-item {{ request()->routeIs('inventory.*') ? 'nav-item-active' : '' }} group"
                   aria-label="{{ __('messages.inventory') }}">
                    <svg class="w-5 h-5 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                    </svg>
                    <span>{{ __('messages.inventory') }}</span>
                </a>

                <!-- More -->
                <a href="{{ route('settings.index') }}"
                   class="nav-item {{ request()->routeIs('settings.*') || request()->routeIs('ledger.*') || request()->routeIs('people.*') ? 'nav-item-active' : '' }} group"
                   aria-label="{{ __('messages.more') }}">
                    <svg class="w-5 h-5 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>{{ __('messages.more') }}</span>
                </a>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @stack('scripts')
</body>
</html>