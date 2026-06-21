@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('ledger.index') }}" class="text-gray-500 dark:text-gray-400 text-sm">&larr; بېرته</a>
    </div>

    <!-- Person Info Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <h2 class="text-lg font-semibold">{{ $person->name }}</h2>
                    <span class="text-xs px-2 py-0.5 rounded-full 
                        {{ $personType === 'customer' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300' }}">
                        {{ $personLabel }}
                    </span>
                </div>
                @if ($person->phone)
                    <p class="text-sm text-gray-500 dark:text-gray-400">تلیفون: {{ $person->phone }}</p>
                @endif
                @if ($person->address)
                    <p class="text-sm text-gray-500 dark:text-gray-400">پته: {{ $person->address }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">افغاني (افغ)</div>
            <div class="space-y-1">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">ټوله:</span>
                    <span class="font-medium">{{ number_format($totalAFN) }} افغ</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-green-600 dark:text-green-400">ورکړل شوي:</span>
                    <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($paidAFN) }} افغ</span>
                </div>
                <div class="flex justify-between text-sm border-t border-gray-100 dark:border-gray-700 pt-1">
                    <span class="font-semibold">پاتې:</span>
                    <span class="font-bold {{ $totalAFN - $paidAFN > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ number_format(max(0, $totalAFN - $paidAFN)) }} افغ
                    </span>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">ډالر ($)</div>
            <div class="space-y-1">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">ټوله:</span>
                    <span class="font-medium">{{ number_format($totalUSD) }}$</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-green-600 dark:text-green-400">ورکړل شوي:</span>
                    <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($paidUSD) }}$</span>
                </div>
                <div class="flex justify-between text-sm border-t border-gray-100 dark:border-gray-700 pt-1">
                    <span class="font-semibold">پاتې:</span>
                    <span class="font-bold {{ $totalUSD - $paidUSD > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ number_format(max(0, $totalUSD - $paidUSD)) }}$
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-sm">
            د پیسو تاریخچه
        </div>
        @forelse ($entries as $entry)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div>
                    <div class="text-sm font-medium">{{ $entry->created_at->format('Y/m/d H:i') }}</div>
                    @if ($entry->notes)
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $entry->notes }}</div>
                    @endif
                </div>
                <div class="text-left">
                    <span class="text-sm font-bold {{ $entry->type === 'payment_received' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $entry->type === 'payment_received' ? '+' : '-' }}{{ number_format($entry->amount) }} {{ $entry->currency === 'USD' ? '$' : 'افغ' }}
                    </span>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه پیسې ثبت شوي ندي
            </div>
        @endforelse
    </div>

    <!-- Add Payment Form -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="font-semibold text-sm mb-3">نوې پیسې ثبتول</h3>
        <form action="{{ route('ledger.payment.store', [$personType, $person->id]) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">د پیسو واحد</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer has-[:checked]:border-[#0d9488] has-[:checked]:bg-teal-50 dark:has-[:checked]:bg-teal-900/20">
                        <input type="radio" name="currency" value="AFN" checked class="text-[#0d9488]">
                        <span class="text-sm">افغاني (افغ)</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer has-[:checked]:border-[#0d9488] has-[:checked]:bg-teal-50 dark:has-[:checked]:bg-teal-900/20">
                        <input type="radio" name="currency" value="USD" class="text-[#0d9488]">
                        <span class="text-sm">ډالر ($)</span>
                    </label>
                </div>
                @error('currency') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">د پیسو اندازه</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required placeholder="مبلغ"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-[#0d9488] focus:border-transparent">
                @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">یادښت (اختیاري)</label>
                <input type="text" name="notes" value="{{ old('notes') }}" placeholder="یادښت"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-[#0d9488] focus:border-transparent">
                @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="w-full bg-[#0d9488] text-gray-800 dark:text-gray-200 py-3 rounded-lg text-sm font-medium">
                + پیسې ثبتول
            </button>
        </form>
    </div>
@endsection