@php
use App\Models\Setting;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ps', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, minimum-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MGS - {{ __('messages.dashboard') }}</title>
    @fonts
    @if(in_array(app()->getLocale(), ['ps', 'fa']))
        @vite(['resources/css/app-rtl.css', 'resources/js/app.js', 'resources/js/ui.js'])
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/ui.js'])
    @endif
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/favicon.svg">
</head>
<body class="bg-ink-50 dark:bg-[#0b0c0e] text-ink-700 dark:text-ink-200 font-sans antialiased">
    <div class="min-h-screen flex flex-col pb-[72px]">

        <!-- HEADER -->
        <header class="bg-white/90 dark:bg-[#18191a]/90 backdrop-blur-xl border-b border-ink-100 dark:border-white/[0.05] sticky top-0 z-40">
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <button type="button" id="sidebar-toggle" aria-label="{{ __('messages.features') }}"
                            class="w-8 h-8 rounded-xl bg-brand text-white flex items-center justify-center shadow-sm active:scale-95 transition-transform">
                        <x-icon name="bars-3" class="w-5 h-5" strokeWidth="1.8"/>
                    </button>
                    <div>
                        <h1 class="text-sm font-bold text-ink-900 dark:text-white">MGS</h1>
                        <span class="text-[10px] text-ink-500 dark:text-ink-400">{{ $headerDescription ?? __('messages.dashboard') }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative">
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
                        <x-icon name="app-icon" class="w-5 h-5"/>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-ink-900 dark:text-white">MGS</h2>
                        <span class="text-[10px] text-ink-500 dark:text-ink-400">{{ __('messages.features') }}</span>
                    </div>
                </div>
                <button type="button" id="sidebar-close" class="w-8 h-8 rounded-xl flex items-center justify-center text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 hover:bg-ink-100 dark:hover:bg-ink-700 transition-colors">
                    <x-icon name="x-mark" class="w-5 h-5" strokeWidth="2"/>
                </button>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('payments.index') }}" class="sidebar-link {{ request()->routeIs('payments.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="wallet" class="w-5 h-5"/>
                    <span>{{ __('messages.wallet') }}</span>
                </a>
                <a href="{{ route('expenses.index') }}" class="sidebar-link {{ request()->routeIs('expenses.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="banknotes" class="w-5 h-5"/>
                    <span>{{ __('messages.expenses') }}</span>
                </a>
                <a href="{{ route('staff.index') }}" class="sidebar-link {{ request()->routeIs('staff.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="users" class="w-5 h-5"/>
                    <span>{{ __('messages.staff_book') }}</span>
                </a>
                <a href="{{ route('passbook.index') }}" class="sidebar-link {{ request()->routeIs('passbook.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="currency-dollar" class="w-5 h-5"/>
                    <span>{{ __('messages.passbook') }}</span>
                </a>
                <a href="{{ route('spend-breakdown.index') }}" class="sidebar-link {{ request()->routeIs('spend-breakdown.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="chart-bar" class="w-5 h-5"/>
                    <span>{{ __('messages.spend_breakdown') }}</span>
                </a>
                <a href="{{ route('app-lock.index') }}" class="sidebar-link {{ request()->routeIs('app-lock.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="lock-closed" class="w-5 h-5"/>
                    <span>{{ __('messages.app_lock') }}</span>
                </a>
                <div class="sidebar-divider"></div>
                <a href="{{ route('reports.daybook') }}" class="sidebar-link {{ request()->routeIs('reports.daybook') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="calendar-days" class="w-5 h-5"/>
                    <span>{{ __('messages.daybook') }}</span>
                </a>
                <a href="{{ route('reports.stock') }}" class="sidebar-link {{ request()->routeIs('reports.stock') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="archive-box" class="w-5 h-5"/>
                    <span>{{ __('messages.stock_report') }}</span>
                </a>
                <a href="{{ route('reports.profit-loss') }}" class="sidebar-link {{ request()->routeIs('reports.profit-loss') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="arrow-trending-up" class="w-5 h-5"/>
                    <span>{{ __('messages.profit_loss_report') }}</span>
                </a>
                <a href="{{ route('reports.balance-sheet') }}" class="sidebar-link {{ request()->routeIs('reports.balance-sheet') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="chart-bar" class="w-5 h-5"/>
                    <span>{{ __('messages.balance_sheet') }}</span>
                </a>
                <a href="{{ route('reports.aging') }}" class="sidebar-link {{ request()->routeIs('reports.aging') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="clock" class="w-5 h-5"/>
                    <span>{{ __('messages.aging_report') }}</span>
                </a>
                <div class="sidebar-divider"></div>
                <a href="{{ route('ledger.index') }}" class="sidebar-link {{ request()->routeIs('ledger.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="document-text" class="w-5 h-5"/>
                    <span>{{ __('messages.ledger') }}</span>
                </a>
                <a href="{{ route('reminders.history') }}" class="sidebar-link {{ request()->routeIs('reminders.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="bell" class="w-5 h-5"/>
                    <span>{{ __('messages.reminders') }}</span>
                </a>
                <a href="{{ route('backup.index') }}" class="sidebar-link {{ request()->routeIs('backup.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="circle-stack" class="w-5 h-5"/>
                    <span>{{ __('messages.backups') }}</span>
                </a>
                <a href="{{ route('settings.index') }}" class="sidebar-link {{ request()->routeIs('settings.*') && !request()->routeIs('settings.openwa.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="cog-6-tooth" class="w-5 h-5"/>
                    <span>{{ __('messages.settings') }}</span>
                </a>
                <a href="{{ route('settings.openwa') }}" class="sidebar-link {{ request()->routeIs('settings.openwa.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="chat-bubble-left-right" class="w-5 h-5"/>
                    <span>{{ __('messages.whatsapp_gateway') }}</span>
                </a>
                <a href="{{ route('whatsapp.chats.index') }}" class="sidebar-link {{ request()->routeIs('whatsapp.chats.*') ? 'sidebar-link-active' : '' }}">
                    <x-icon name="chat-bubble-oval-left-ellipsis" class="w-5 h-5"/>
                    <span>{{ __('messages.whatsapp_chats') }}</span>
                </a>
            </nav>
        </aside>

        <script>
            function changeLanguage(lang) {
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
                        <x-icon name="x-mark" class="w-3.5 h-3.5" strokeWidth="2"/>
                    </button>
                </div>
                <div class="toast-progress" style="animation-duration:4500ms"></div>
            </div>
        @endif
        @if (session('error'))
            <div class="toast" role="alert">
                <div class="flex items-center gap-2.5 bg-white dark:bg-[#1e2127] border border-danger-200 dark:border-danger-800/50 rounded-2xl px-4 py-3 shadow-card">
                    <div class="toast-icon toast-icon-error">
                        <x-icon name="x-mark" class="w-3.5 h-3.5" strokeWidth="2.5"/>
                    </div>
                    <p class="text-sm font-medium text-ink-800 dark:text-ink-200 flex-1">{{ session('error') }}</p>
                    <button onclick="this.closest('.toast').remove()" class="text-ink-400 hover:text-ink-600 dark:hover:text-ink-300 transition-colors p-1">
                        <x-icon name="x-mark" class="w-3.5 h-3.5" strokeWidth="2"/>
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
        <div class="bg-white/90 dark:bg-[#18191a]/90 backdrop-blur-2xl border border-ink-100 dark:border-white/[0.05] rounded-[1.25rem] shadow-nav dark:shadow-nav-dark">
            <div class="relative flex items-center justify-around py-1">
            <span id="nav-pill" class="nav-pill"></span>
                <!-- Dashboard -->
                <a href="{{ route('dashboard') }}"
                   class="nav-item {{ request()->routeIs('dashboard') ? 'nav-item-active' : '' }} group"
                   aria-label="{{ __('messages.dashboard') }}">
                    <x-icon name="squares-2x2" class="w-5 h-5"/>
                    <span>{{ __('messages.dashboard') }}</span>
                </a>

                <!-- Orders -->
                <a href="{{ route('orders.index') }}"
                   class="nav-item {{ request()->routeIs('orders.*') ? 'nav-item-active' : '' }} group"
                   aria-label="{{ __('messages.orders') }}">
                    <x-icon name="clipboard-document-list" class="w-5 h-5"/>
                    <span>{{ __('messages.orders') }}</span>
                </a>

                <!-- Center FAB: expandable quick-actions menu -->
                <button type="button" id="fab-btn"
                        class="nav-item group -mt-2 relative"
                        aria-label="{{ __('messages.quick_actions') }}" aria-expanded="false" aria-haspopup="true">
                    <div class="w-10 h-10 rounded-full brand-grad text-white flex items-center justify-center shadow-fab">
                        <x-icon name="plus" class="w-5 h-5 text-white fab-toggle transition-transform duration-300" strokeWidth="2"/>
                    </div>
                    <span class="text-primary-600 dark:text-primary-400 font-bold">{{ __('messages.quick_actions') }}</span>
                </button>

                <!-- FAB popup menu -->
                <div class="fab-menu" id="fab-menu" role="menu" aria-label="{{ __('messages.quick_actions') }}">
                    <a href="{{ route('orders.create') }}" class="fab-menu-item" role="menuitem">
                        <x-icon name="plus" class="w-5 h-5"/>
                        <span>{{ __('messages.new_order') }}</span>
                    </a>
                    <a href="{{ route('expenses.create') }}" class="fab-menu-item" role="menuitem">
                        <x-icon name="currency-dollar" class="w-5 h-5"/>
                        <span>{{ __('messages.new_expense') }}</span>
                    </a>
                    <a href="{{ route('purchases.create') }}" class="fab-menu-item" role="menuitem">
                        <x-icon name="shopping-cart" class="w-5 h-5"/>
                        <span>{{ __('messages.new_purchase') }}</span>
                    </a>
                    <a href="{{ route('cashbook.index') }}" class="fab-menu-item" role="menuitem">
                        <x-icon name="banknotes" class="w-5 h-5"/>
                        <span>{{ __('messages.cashbook') }}</span>
                    </a>
                </div>
                <div class="fab-backdrop" id="fab-backdrop"></div>

                <!-- Inventory -->
                <a href="{{ route('inventory.index') }}"
                   class="nav-item {{ request()->routeIs('inventory.*') ? 'nav-item-active' : '' }} group"
                   aria-label="{{ __('messages.inventory') }}">
                    <x-icon name="archive-box" class="w-5 h-5"/>
                    <span>{{ __('messages.inventory') }}</span>
                </a>

                <!-- More -->
                <a href="{{ route('settings.index') }}"
                   class="nav-item {{ request()->routeIs('settings.*') || request()->routeIs('ledger.*') || request()->routeIs('people.*') ? 'nav-item-active' : '' }} group"
                   aria-label="{{ __('messages.more') }}">
                    <x-icon name="cog-6-tooth" class="w-5 h-5"/>
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

    @stack('scripts')
</body>
</html>