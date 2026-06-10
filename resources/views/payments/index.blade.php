@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">پیسې</h2>
        <a href="{{ route('payments.create') }}" class="bg-[#f53003] text-white px-4 py-2 rounded-lg text-sm font-medium">
            + نوی پیسې
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse ($payments as $payment)
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium">{{ $payment->order->customer->name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">امر #{{ $payment->order_id }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-green-600 dark:text-green-400">{{ number_format($payment->amount) }} افغ</span>
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
        $totalPayments = $payments->sum('amount');
    @endphp
    @if ($payments->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mt-4">
            <div class="flex justify-between text-sm">
                <span class="font-semibold">ټولې ثبت شوې پیسې:</span>
                <span class="font-bold text-green-600 dark:text-green-400">{{ number_format($totalPayments) }} افغ</span>
            </div>
        </div>
    @endif
@endsection