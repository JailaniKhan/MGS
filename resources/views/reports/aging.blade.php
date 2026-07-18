@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.aging_report') }}</h2>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form method="GET" action="{{ route('reports.aging') }}" class="grid grid-cols-2 gap-3">
            <div>
                <label class="form-label !text-xs">{{ __('messages.currency') }}</label>
                <select name="currency" class="form-input">
                    <option value="AFN" {{ $selectedCurrency === 'AFN' ? 'selected' : '' }}>{{ __('messages.afn') }}</option>
                    <option value="USD" {{ $selectedCurrency === 'USD' ? 'selected' : '' }}>{{ __('messages.usd') }}</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn-primary mt-5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-secondary-50 dark:bg-secondary-900/10">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.customer_receivables') }}</h3>
            </div>
        </div>
        <div class="px-4 py-3 flex items-center justify-between border-b border-ink-100 dark:border-ink-700/30">
            <span class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.grand_total') }}</span>
            <div class="flex gap-3 text-[11px]">
                <span>0-30: <strong>{{ number_format($customerBucketTotal['0-30'], 2) }}</strong></span>
                <span>31-60: <strong>{{ number_format($customerBucketTotal['31-60'], 2) }}</strong></span>
                <span>61-90: <strong>{{ number_format($customerBucketTotal['61-90'], 2) }}</strong></span>
                <span class="text-danger-600">90+: <strong>{{ number_format($customerBucketTotal['90+'], 2) }}</strong></span>
            </div>
        </div>
    </div>

    @foreach($customerAging as $aging)
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/50">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ $aging['customer']->name }}</span>
                    <div class="flex gap-2 text-[11px]">
                        <span>0-30: {{ number_format($aging['bucket_totals']['0-30'], 2) }}</span>
                        <span>31-60: {{ number_format($aging['bucket_totals']['31-60'], 2) }}</span>
                        <span>61-90: {{ number_format($aging['bucket_totals']['61-90'], 2) }}</span>
                        <span class="text-danger-600">90+: {{ number_format($aging['bucket_totals']['90+'], 2) }}</span>
                        <span class="font-bold">{{ number_format($aging['total_outstanding'], 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span>
                    </div>
                </div>
            </div>
            @if($aging['orders']->isNotEmpty())
                @foreach($aging['orders'] as $order)
                    <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                        <div>
                            <span class="text-sm text-ink-800 dark:text-ink-200">{{ __('messages.order') }} #{{ $order['order_id'] }}</span>
                            <span class="text-xs text-ink-500 dark:text-ink-400 block">{{ $order['order_date'] }} - {{ $order['days'] }} {{ __('messages.days') }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ number_format($order['outstanding'], 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span>
                            <span class="text-xs text-ink-500 dark:text-ink-400 block">{{ $order['bucket'] }}</span>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-accent-50 dark:bg-accent-900/10">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-5 rounded-full bg-primary-500"></div>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.supplier_payables') }}</h3>
            </div>
        </div>
        <div class="px-4 py-3 flex items-center justify-between border-b border-ink-100 dark:border-ink-700/30">
            <span class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.grand_total') }}</span>
            <div class="flex gap-3 text-[11px]">
                <span>0-30: <strong>{{ number_format($supplierBucketTotal['0-30'], 2) }}</strong></span>
                <span>31-60: <strong>{{ number_format($supplierBucketTotal['31-60'], 2) }}</strong></span>
                <span>61-90: <strong>{{ number_format($supplierBucketTotal['61-90'], 2) }}</strong></span>
                <span class="text-danger-600">90+: <strong>{{ number_format($supplierBucketTotal['90+'], 2) }}</strong></span>
            </div>
        </div>
    </div>

    @foreach($supplierAging as $aging)
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.25s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/50">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ $aging['supplier']->name }}</span>
                    <div class="flex gap-2 text-[11px]">
                        <span>0-30: {{ number_format($aging['bucket_totals']['0-30'], 2) }}</span>
                        <span>31-60: {{ number_format($aging['bucket_totals']['31-60'], 2) }}</span>
                        <span>61-90: {{ number_format($aging['bucket_totals']['61-90'], 2) }}</span>
                        <span class="text-danger-600">90+: {{ number_format($aging['bucket_totals']['90+'], 2) }}</span>
                        <span class="font-bold">{{ number_format($aging['total_outstanding'], 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span>
                    </div>
                </div>
            </div>
            @if($aging['purchases']->isNotEmpty())
                @foreach($aging['purchases'] as $purchase)
                    <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 last:border-b-0">
                        <div>
                            <span class="text-sm text-ink-800 dark:text-ink-200">{{ __('messages.purchase') }} #{{ $purchase['purchase_id'] }}</span>
                            <span class="text-xs text-ink-500 dark:text-ink-400 block">{{ $purchase['purchase_date'] }} - {{ $purchase['days'] }} {{ __('messages.days') }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ number_format($purchase['outstanding'], 2) }} {{ $selectedCurrency === 'USD' ? '$' : __('messages.afn') }}</span>
                            <span class="text-xs text-ink-500 dark:text-ink-400 block">{{ $purchase['bucket'] }}</span>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach

    @if($customerAging->isEmpty() && $supplierAging->isEmpty())
        <div class="empty-state">
            <p class="text-sm text-ink-500 dark:text-ink-400">{{ __('messages.no_debt_found') }}</p>
        </div>
    @endif
@endsection