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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/ui.js'])
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
                    <button type="button" id="sidebar-toggle" aria-label="{{ __('messages.features') }}"
                            class="w-8 h-8 rounded-xl bg-brand text-white flex items-center justify-center shadow-sm active:scale-95 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </button>
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

        <!-- SIDEBAR DRAWER -->
        <div id="sidebar-backdrop" class="sidebar-backdrop"></div>
        <aside id="sidebar-drawer" class="sidebar-drawer" aria-hidden="true">
            <div class="sidebar-header">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-brand text-white flex items-center justify-center shadow-sm">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 5.5A1.5 1.5 0 015.5 4h13A1.5 1.5 0 0120 5.5v13A1.5 1.5 0 0118.5 20h-13A1.5 1.5 0 014 18.5v-13z"/>
                            <path d="M4 9.5h16"/>
                            <path d="M8 4v16"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-ink-900 dark:text-white">MGS</h2>
                        <span class="text-[10px] text-ink-500 dark:text-ink-400">{{ __('messages.features') }}</span>
                    </div>
                </div>
                <button type="button" id="sidebar-close" class="w-8 h-8 rounded-xl flex items-center justify-center text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 hover:bg-ink-100 dark:hover:bg-ink-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('payments.index') }}" class="sidebar-link {{ request()->routeIs('payments.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"/></svg>
                    <span>{{ __('messages.wallet') }}</span>
                </a>
                <a href="{{ route('staff.index') }}" class="sidebar-link {{ request()->routeIs('staff.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    <span>{{ __('messages.staff_book') }}</span>
                </a>
                <a href="{{ route('passbook.index') }}" class="sidebar-link {{ request()->routeIs('passbook.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ __('messages.passbook') }}</span>
                </a>
                <a href="{{ route('spend-breakdown.index') }}" class="sidebar-link {{ request()->routeIs('spend-breakdown.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    <span>{{ __('messages.spend_breakdown') }}</span>
                </a>
                <a href="{{ route('app-lock.index') }}" class="sidebar-link {{ request()->routeIs('app-lock.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    <span>{{ __('messages.app_lock') }}</span>
                </a>
                <div class="sidebar-divider"></div>
                <a href="{{ route('reports.daybook') }}" class="sidebar-link {{ request()->routeIs('reports.daybook') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                    <span>{{ __('messages.daybook') }}</span>
                </a>
                <a href="{{ route('reports.stock') }}" class="sidebar-link {{ request()->routeIs('reports.stock') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                    <span>{{ __('messages.stock_report') }}</span>
                </a>
                <a href="{{ route('reports.profit-loss') }}" class="sidebar-link {{ request()->routeIs('reports.profit-loss') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
                    <span>{{ __('messages.profit_loss_report') }}</span>
                </a>
                <a href="{{ route('reports.balance-sheet') }}" class="sidebar-link {{ request()->routeIs('reports.balance-sheet') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    <span>{{ __('messages.balance_sheet') }}</span>
                </a>
                <a href="{{ route('reports.aging') }}" class="sidebar-link {{ request()->routeIs('reports.aging') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ __('messages.aging_report') }}</span>
                </a>
                <div class="sidebar-divider"></div>
                <a href="{{ route('ledger.index') }}" class="sidebar-link {{ request()->routeIs('ledger.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    <span>{{ __('messages.ledger') }}</span>
                </a>
                <a href="{{ route('reminders.history') }}" class="sidebar-link {{ request()->routeIs('reminders.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                    <span>{{ __('messages.reminders') }}</span>
                </a>
                <a href="{{ route('backup.index') }}" class="sidebar-link {{ request()->routeIs('backup.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                    <span>{{ __('messages.backups') }}</span>
                </a>
                <a href="{{ route('settings.index') }}" class="sidebar-link {{ request()->routeIs('settings.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>{{ __('messages.settings') }}</span>
                </a>
            </nav>
        </aside>

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
                    <div class="toast-icon toast-icon-success">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path class="check-pop" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-ink-800 dark:text-ink-200 flex-1">{{ session('success') }}</p>
                    <button onclick="this.closest('.toast').remove()" class="text-ink-400 hover:text-ink-600 dark:hover:text-ink-300 transition-colors p-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="toast-progress" style="animation-duration:4500ms"></div>
            </div>
        @endif
        @if (session('error'))
            <div class="toast" role="alert">
                <div class="flex items-center gap-2.5 bg-white dark:bg-[#1e2127] border border-danger-200 dark:border-danger-800/50 rounded-2xl px-4 py-3 shadow-card">
                    <div class="toast-icon toast-icon-error">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <div class="toast-progress" style="animation-duration:4500ms"></div>
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

                <!-- Center FAB: expandable quick-actions menu -->
                <button type="button" id="fab-btn"
                        class="nav-item group -mt-2 relative"
                        aria-label="{{ __('messages.quick_actions') }}" aria-expanded="false" aria-haspopup="true">
                    <div class="w-10 h-10 rounded-full brand-grad text-white flex items-center justify-center shadow-fab">
                        <svg class="w-5 h-5 text-white fab-toggle transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                        </svg>
                    </div>
                    <span class="text-primary-600 dark:text-primary-400 font-bold">{{ __('messages.quick_actions') }}</span>
                </button>

                <!-- FAB popup menu -->
                <div class="fab-menu" id="fab-menu" role="menu" aria-label="{{ __('messages.quick_actions') }}">
                    <a href="{{ route('orders.create') }}" class="fab-menu-item" role="menuitem">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        <span>{{ __('messages.new_order') }}</span>
                    </a>
                    <a href="{{ route('expenses.create') }}" class="fab-menu-item" role="menuitem">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ __('messages.new_expense') }}</span>
                    </a>
                    <a href="{{ route('purchases.create') }}" class="fab-menu-item" role="menuitem">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
                        <span>{{ __('messages.new_purchase') }}</span>
                    </a>
                    <a href="{{ route('cashbook.index') }}" class="fab-menu-item" role="menuitem">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 9.75A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25V10.5A2.25 2.25 0 0018.75 8.25H5.25A2.25 2.25 0 003 10.5v8.25z"/></svg>
                        <span>{{ __('messages.cashbook') }}</span>
                    </a>
                </div>
                <div class="fab-backdrop" id="fab-backdrop"></div>

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

    <!-- Navigation skeleton overlay (instant feedback on link/submit) -->
    <div class="skeleton-overlay" id="page-skeleton" hidden>
        <div class="h-14 border-b border-ink-100 dark:border-white/[0.06]"></div>
        <div class="pt-4 space-y-3">
            <div class="skeleton-block shimmer-bg bg-ink-100 dark:bg-white/[0.06] h-28 rounded-3xl"></div>
            <div class="grid grid-cols-2 gap-3">
                @include('components.skeleton-card')
                @include('components.skeleton-card')
            </div>
            <div class="skeleton-block shimmer-bg bg-ink-100 dark:bg-white/[0.06] h-40 rounded-2xl"></div>
            <div class="skeleton-block shimmer-bg bg-ink-100 dark:bg-white/[0.06] h-32 rounded-2xl"></div>
        </div>
    </div>

    <!-- Global confirm / action sheet (mobile bottom-sheet) -->
    <div id="confirm-sheet" class="sheet-backdrop" aria-hidden="true">
        <div class="bottom-sheet" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
            <div class="sheet-handle"></div>
            <div class="px-5 pb-5">
                <h3 id="confirm-title" class="text-base font-bold text-ink-900 dark:text-white">{{ __('messages.are_you_sure') }}</h3>
                <p id="confirm-message" class="text-sm text-ink-500 dark:text-ink-400 mt-1 leading-relaxed"></p>
                <div class="confirm-actions">
                    <button id="confirm-cancel" class="btn-ghost" type="button">{{ __('messages.cancel') }}</button>
                    <button id="confirm-ok" class="btn-danger" type="button">{{ __('messages.delete') }}</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @stack('scripts')
</body>
</html>