@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('purchases.index') }}" class="text-gray-500 dark:text-gray-400 text-sm">&larr; بېرته</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <div class="flex justify-between items-start mb-3">
            <div>
                <h2 class="text-lg font-semibold">خرید #{{ $purchase->id }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $purchase->created_at->format('Y/m/d H:i') }}</p>
            </div>
            <span class="inline-block text-sm px-3 py-1 rounded-full 
                @if($purchase->status === 'completed') bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300
                @elseif($purchase->status === 'processing') bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300
                @elseif($purchase->status === 'cancelled') bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300
                @else bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 @endif">
                @switch($purchase->status)
                    @case('completed') بشپړ @break
                    @case('processing') پروسس @break
                    @case('cancelled') لغوه @break
                    @default پاتې
                @endswitch
            </span>
        </div>

        <div class="text-sm mb-1">
            <span class="text-gray-500 dark:text-gray-400">پلورونکی: </span>
            <span class="font-medium">{{ $purchase->supplier->name }}</span>
        </div>

        <div class="text-sm mb-1">
            <span class="text-gray-500 dark:text-gray-400">د پیسو واحد: </span>
            <span class="font-medium">{{ $purchase->currency === 'USD' ? 'ډالر ($)' : 'افغاني (افغ)' }}</span>
        </div>

        @if ($purchase->supplier->phone)
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">تلیفون: {{ $purchase->supplier->phone }}</p>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-sm">
            د خرید توکي
        </div>
        @foreach ($purchase->purchaseItems as $item)
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                <div>
                    <div class="text-sm font-medium">{{ $item->product->name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $item->quantity }}@if($item->product->unit) {{ $item->product->unit->short_name ?? $item->product->unit->name }}@endif x {{ number_format($item->unit_price) }} {{ $purchase->currency === 'USD' ? '$' : 'افغ' }}
                    </div>
                </div>
                <div class="text-sm font-semibold">{{ number_format($item->subtotal) }} {{ $purchase->currency === 'USD' ? '$' : 'افغ' }}</div>
            </div>
        @endforeach
        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700 font-bold">
            <span>ټوله</span>
            <span>{{ number_format($purchase->total_amount) }} {{ $purchase->currency === 'USD' ? '$' : 'افغ' }}</span>
        </div>
    </div>

    <!-- Payment Summary -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <h3 class="font-semibold text-sm mb-3">د پیسو ورکړه</h3>
        <div class="space-y-2 text-sm mb-3">
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">ټوله بیه:</span>
                <span class="font-medium">{{ number_format($purchase->total_amount) }} {{ $purchase->currency === 'USD' ? '$' : 'افغ' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">ورکړل شوي:</span>
                <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($purchase->paid_amount) }} {{ $purchase->currency === 'USD' ? '$' : 'افغ' }}</span>
            </div>
            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2">
                <span class="font-semibold">پاتې پیسې:</span>
                <span class="font-bold {{ $purchase->is_fully_paid ? 'text-green-600 dark:text-green-400' : 'text-[#0d9488]' }}">
                    {{ $purchase->is_fully_paid ? 'بشپړ شوی' : number_format($purchase->remaining_amount) . ' ' . ($purchase->currency === 'USD' ? '$' : 'افغ') }}
                </span>
            </div>
        </div>

        @if ($purchase->purchasePayments->count() > 0)
            <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mb-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">د پیسو تاریخچه:</p>
                @foreach ($purchase->purchasePayments as $payment)
                    <div class="flex justify-between items-center py-1.5 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                        <div>
                            <span class="text-xs">{{ $payment->created_at->format('Y/m/d H:i') }}</span>
                            @if ($payment->notes)
                                <span class="text-xs text-gray-400 dark:text-gray-500 mr-1">({{ $payment->notes }})</span>
                            @endif
                        </div>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">{{ number_format($payment->amount) }} {{ $payment->currency === 'USD' ? '$' : 'افغ' }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if (!$purchase->is_fully_paid && $purchase->status !== 'cancelled')
            <form action="{{ route('purchases.payment.store') }}" method="POST" class="border-t border-gray-200 dark:border-gray-700 pt-3">
                @csrf
                <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">
                <input type="hidden" name="currency" value="{{ $purchase->currency }}">
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">مبلغ</label>
                    <input type="number" name="amount" step="0.01" min="0.01" max="{{ $purchase->remaining_amount }}" required placeholder="مبلغ"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-[#0d9488] focus:border-transparent">
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">یادښت (اختیاري)</label>
                    <input type="text" name="notes" placeholder="یادښت"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-[#0d9488] focus:border-transparent">
                </div>
                <button type="submit" class="w-full text-center bg-[#0d9488] text-gray-800  py-2 rounded-lg text-sm font-medium">
                    + پیسې ورکول
                </button>
            </form>
        @endif
    </div>

    @if ($purchase->status !== 'cancelled')
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <h3 class="font-semibold text-sm mb-3">د حالت بدلول</h3>
        <div class="flex flex-wrap gap-2">
            <form action="{{ route('purchases.status', [$purchase, 'pending']) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit" class="w-full text-center px-3 py-2 rounded-lg text-sm font-medium {{ $purchase->status === 'pending' ? 'bg-yellow-500 text-white' : 'bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300' }}">
                    پاتې
                </button>
            </form>
            <form action="{{ route('purchases.status', [$purchase, 'processing']) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit" class="w-full text-center px-3 py-2 rounded-lg text-sm font-medium {{ $purchase->status === 'processing' ? 'bg-blue-500 text-white' : 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' }}">
                    پروسس
                </button>
            </form>
            <form action="{{ route('purchases.status', [$purchase, 'completed']) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit" class="w-full text-center px-3 py-2 rounded-lg text-sm font-medium {{ $purchase->status === 'completed' ? 'bg-green-500 text-white' : 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' }}">
                    بشپړ
                </button>
            </form>
            <form action="{{ route('purchases.status', [$purchase, 'cancelled']) }}" method="POST" class="flex-1" onsubmit="return confirm('آیا ډاډه یاست چې غواړئ دا خرید لغوه کړئ؟')">
                @csrf
                <button type="submit" class="w-full text-center px-3 py-2 rounded-lg text-sm font-medium {{ $purchase->status === 'cancelled' ? 'bg-red-500 text-white' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' }}">
                    لغوه
                </button>
            </form>
        </div>
    </div>
    @endif

    @if ($purchase->status !== 'cancelled')
        <form action="{{ route('purchases.destroy', $purchase) }}" method="POST" onsubmit="return confirm('آیا ډاډه یاست چې غواړئ دا خرید ړنګ کړئ؟')">
            @csrf @method('DELETE')
            <button class="w-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 py-3 rounded-lg text-sm font-medium">
                خرید ړنګول
            </button>
        </form>
    @endif
@endsection
