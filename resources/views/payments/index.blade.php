@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">والیټ</h2>
    </div>

    <!-- Balance Cards -->
    <div class="space-y-3 mb-6">
        <div class="bg-teal-50 dark:bg-teal-900/30 rounded-xl p-5 shadow-sm border border-teal-200 dark:border-teal-700">
            <div class="text-xs text-teal-700 dark:text-teal-400 mb-1">ټوله بیلانس</div>
            <div class="flex items-center gap-4">
                <div class="text-2xl font-bold text-teal-600 dark:text-teal-300">{{ number_format($balanceAFN, 2) }} افغ</div>
                <div class="text-xl font-bold text-teal-500 dark:text-teal-400">{{ number_format($balanceUSD, 2) }} $</div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="bg-green-50 dark:bg-green-900/30 rounded-xl p-4 shadow-sm border border-green-200 dark:border-green-700">
                <div class="text-xs text-green-700 dark:text-green-400 mb-1">راغلې پیسې</div>
                <div class="text-lg font-bold text-green-600 dark:text-green-300">{{ number_format($incomingAFN, 2) }} افغ</div>
                <div class="text-base font-semibold text-green-500 dark:text-green-400">{{ number_format($incomingUSD, 2) }} $</div>
            </div>
            <div class="bg-red-50 dark:bg-red-900/30 rounded-xl p-4 shadow-sm border border-red-200 dark:border-red-700">
                <div class="text-xs text-red-700 dark:text-red-400 mb-1">وتلې پیسې</div>
                <div class="text-lg font-bold text-red-600 dark:text-red-300">{{ number_format($outgoingAFN, 2) }} افغ</div>
                <div class="text-base font-semibold text-red-500 dark:text-red-400">{{ number_format($outgoingUSD, 2) }} $</div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-sm">
            وروستۍ راکړې ورکړې
        </div>
        @forelse ($transactions as $tx)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $tx['type'] === 'incoming' ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900' }}">
                        @if ($tx['type'] === 'incoming')
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        @else
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                            </svg>
                        @endif
                    </div>
                    <div>
                        <div class="text-sm font-medium">{{ $tx['description'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $tx['date']->format('Y/m/d H:i') }}
                            @if ($tx['notes'])
                                | {{ $tx['notes'] }}
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-left">
                    <span class="text-sm font-bold {{ $tx['type'] === 'incoming' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $tx['type'] === 'incoming' ? '+' : '-' }}{{ number_format($tx['amount'], 2) }} {{ $tx['currency'] === 'USD' ? '$' : 'افغ' }}
                    </span>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه راکړې ورکړې نشته
            </div>
        @endforelse
    </div>
@endsection