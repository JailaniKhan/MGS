<!DOCTYPE html>
<html lang="ps" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MGS - د امر مدیریت سیستم</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 font-sans antialiased">
    <div class="min-h-screen flex flex-col pb-16">
        <!-- Header -->
        <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="px-4 py-3 flex items-center justify-between">
                <h1 class="text-lg font-bold text-[#f53003]">MGS</h1>
                <span class="text-sm text-gray-500 dark:text-gray-400">د امر مدیریت سیستم</span>
            </div>
        </header>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mx-4 mt-3 px-4 py-3 bg-green-100 dark:bg-green-900 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-200 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mx-4 mt-3 px-4 py-3 bg-red-100 dark:bg-red-900 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-200 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        <!-- Main Content -->
        <main class="flex-1 p-4">
            @yield('content')
        </main>
    </div>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-lg z-50">
        <div class="flex items-center justify-around py-2">
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center px-3 py-1 text-xs {{ request()->routeIs('dashboard') ? 'text-[#f53003]' : 'text-gray-500 dark:text-gray-400' }}">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>کور</span>
            </a>
            <a href="{{ route('orders.index') }}" class="flex flex-col items-center px-3 py-1 text-xs {{ request()->routeIs('orders.*') ? 'text-[#f53003]' : 'text-gray-500 dark:text-gray-400' }}">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <span>امرونه</span>
            </a>
            <a href="{{ route('products.index') }}" class="flex flex-col items-center px-3 py-1 text-xs {{ request()->routeIs('products.*') ? 'text-[#f53003]' : 'text-gray-500 dark:text-gray-400' }}">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <span>محصولات</span>
            </a>
            <a href="{{ route('customers.index') }}" class="flex flex-col items-center px-3 py-1 text-xs {{ request()->routeIs('customers.*') ? 'text-[#f53003]' : 'text-gray-500 dark:text-gray-400' }}">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span>ګیراکان</span>
            </a>
            <a href="{{ route('payments.index') }}" class="flex flex-col items-center px-3 py-1 text-xs {{ request()->routeIs('payments.*') ? 'text-[#f53003]' : 'text-gray-500 dark:text-gray-400' }}">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span>پیسې</span>
            </a>
        </div>
    </nav>

    @stack('scripts')
</body>
</html>
