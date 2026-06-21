@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">روزنامچه</h2>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse ($people as $person)
            <a href="{{ route('ledger.show', [$person['type'], $person['id']]) }}" class="block px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium">{{ $person['name'] }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full 
                                {{ $person['type'] === 'customer' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300' }}">
                                {{ $person['type_label'] }}
                            </span>
                        </div>
                        @if ($person['phone'])
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $person['phone'] }}</div>
                        @endif
                    </div>
                    <div class="text-left">
                        @if ($person['remaining_afn'] > 0)
                            <div class="text-sm font-semibold {{ $person['type'] === 'customer' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ number_format($person['remaining_afn']) }} افغ
                            </div>
                        @endif
                        @if ($person['remaining_usd'] > 0)
                            <div class="text-sm font-semibold {{ $person['type'] === 'customer' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ number_format($person['remaining_usd']) }}$
                            </div>
                        @endif
                        @if ($person['remaining_afn'] <= 0 && $person['remaining_usd'] <= 0)
                            <span class="text-xs text-gray-400 dark:text-gray-500">بشپړ شوی</span>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه ګیراک یا پلورونکی نشته
            </div>
        @endforelse
    </div>
@endsection