@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">پیسې</h2>
        <a href="{{ route('payments.create') }}" class="bg-[#0d9488] text-white px-4 py-2 rounded-lg text-sm font-medium">
            + نوی پیسې
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden divide-y divide-gray-100 dark:divide-gray-700">
        <div class="px-4 py-3">
            <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">د پیسو :</div>
        </div>
        <div class="px-4 py-3">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">د اونته یا incomplete امرونو :</span>
                <div class="text-left">
                    <div class="font-bold text-red-600 dark:text-red-400">{{ number_format($pendingUnfulfilledTotalAFN, 2) }} افغ</div>
                    @if ($pendingUnfulfilledTotalUSD > 0)
                        <div class="font-bold text-red-600 dark:text-red-400">{{ number_format($pendingUnfulfilledTotalUSD, 2) }} $</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="px-4 py-3">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">د حساب سره ثبت شوي :</span>
                <div class="text-left">
                    <div class="font-bold text-green-600 dark:text-green-400">{{ number_format($totalReceivedAFN, 2) }} افغ</div>
                    @if ($totalReceivedUSD > 0)
                        <div class="font-bold text-green-600 dark:text-green-400">{{ number_format($totalReceivedUSD, 2) }} $</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="px-4 py-3">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">ساده شوی مگر تر اوسه نه دی :</span>
                <div class="text-left">
                    <div class="font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($outstandingTotalAFN, 2) }} افغ</div>
                    @if ($outstandingTotalUSD > 0)
                        <div class="font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($outstandingTotalUSD, 2) }} $</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mt-4">
        @forelse ($payments as $payment)
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium">{{ $payment->order->customer->name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">امر #{{ $payment->order_id }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-green-600 dark:text-green-400">{{ number_format($payment->amount, 2) }} {{ $payment->currency === 'USD' ? '$' : 'افغ' }}</span>
                        <form action="{{ route('payments.destroy', $payment) }}" method="POST" onsubmit="return confirm('آیا ډاډه یاست؟')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 dark:text-red-400 text-xs">ړنګول</button>
                        </form>
                    </div>
                </div>
                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                    {{ $payment->created_at->format('Y/m/d H:i') }}
                    @if ($payment->notes)
                        | {{ $payment->notes }}
                    @endif
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                لا تر اوسه پیسې ثبت شوي ندي
            </div>
        @endforelse
    </div>

    @php
        $totalPaymentsAFN = $payments->where('currency', 'AFN')->sum('amount');
        $totalPaymentsUSD = $payments->where('currency', 'USD')->sum('amount');
    @endphp
    @if ($payments->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mt-4">
            <div class="flex justify-between text-sm">
                <span class="font-semibold">ټولې ثبت شوې پیسې:</span>
                <div class="text-left">
                    <div class="font-bold text-green-600 dark:text-green-400">{{ number_format($totalPaymentsAFN, 2) }} افغ</div>
                    @if ($totalPaymentsUSD > 0)
                        <div class="font-bold text-green-600 dark:text-green-400">{{ number_format($totalPaymentsUSD, 2) }} $</div>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endsection