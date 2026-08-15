@extends('layouts.app')

@section('content')
    @php
        $bucketBadge = fn (string $bucket) => match ($bucket) {
            '0-30' => 'badge badge-success',
            '31-60' => 'badge badge-info',
            '61-90' => 'badge badge-warning',
            default => 'badge badge-danger',
        };
    @endphp

    <div class="mb-4 page-enter">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
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
                <button type="submit" class="btn-primary mt-5"><x-icon name="check" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.filter') }}</button>
            </div>
        </form>
    </div>

    @if($customerAging->isNotEmpty())
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-secondary-50 dark:bg-secondary-900/10">
                <div class="flex items-center gap-2">
                    <div class="w-1.5 h-5 rounded-full bg-secondary-500"></div>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.customer_receivables') }}</h3>
                </div>
            </div>
            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 dark:border-ink-700/30">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.grand_total') }}</span>
                    <span class="text-lg font-extrabold tabular-nums text-ink-900 dark:text-white">{{ number_format($customerGrandTotal, 2) }} {{ $currencySymbol }}</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <span class="{{ $bucketBadge('0-30') }}">0-30: {{ number_format($customerBucketTotal['0-30'], 2) }}</span>
                    <span class="{{ $bucketBadge('31-60') }}">31-60: {{ number_format($customerBucketTotal['31-60'], 2) }}</span>
                    <span class="{{ $bucketBadge('61-90') }}">61-90: {{ number_format($customerBucketTotal['61-90'], 2) }}</span>
                    <span class="{{ $bucketBadge('90+') }}">90+: {{ number_format($customerBucketTotal['90+'], 2) }}</span>
                </div>
            </div>
        </div>
    @endif

    @foreach($customerAging as $aging)
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.15s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/50">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ $aging['customer']->name }}</span>
                    <div class="flex flex-wrap gap-1.5 items-center">
                        <span class="{{ $bucketBadge('0-30') }}">{{ number_format($aging['bucket_totals']['0-30'], 2) }}</span>
                        <span class="{{ $bucketBadge('31-60') }}">{{ number_format($aging['bucket_totals']['31-60'], 2) }}</span>
                        <span class="{{ $bucketBadge('61-90') }}">{{ number_format($aging['bucket_totals']['61-90'], 2) }}</span>
                        <span class="{{ $bucketBadge('90+') }}">{{ number_format($aging['bucket_totals']['90+'], 2) }}</span>
                        <span class="text-sm font-bold tabular-nums text-ink-900 dark:text-white ms-1">{{ number_format($aging['total_outstanding'], 2) }} {{ $currencySymbol }}</span>
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
                        <div class="text-end flex flex-col items-end gap-1">
                            <span class="text-sm font-semibold tabular-nums text-ink-900 dark:text-ink-100">{{ number_format($order['outstanding'], 2) }} {{ $currencySymbol }}</span>
                            <span class="{{ $bucketBadge($order['bucket']) }}">{{ $order['bucket'] }}</span>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach

    @if($supplierAging->isNotEmpty())
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.2s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-accent-50 dark:bg-accent-900/10">
                <div class="flex items-center gap-2">
                    <div class="w-1.5 h-5 rounded-full bg-accent-500"></div>
                    <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.supplier_payables') }}</h3>
                </div>
            </div>
            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 dark:border-ink-700/30">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.grand_total') }}</span>
                    <span class="text-lg font-extrabold tabular-nums text-ink-900 dark:text-white">{{ number_format($supplierGrandTotal, 2) }} {{ $currencySymbol }}</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <span class="{{ $bucketBadge('0-30') }}">0-30: {{ number_format($supplierBucketTotal['0-30'], 2) }}</span>
                    <span class="{{ $bucketBadge('31-60') }}">31-60: {{ number_format($supplierBucketTotal['31-60'], 2) }}</span>
                    <span class="{{ $bucketBadge('61-90') }}">61-90: {{ number_format($supplierBucketTotal['61-90'], 2) }}</span>
                    <span class="{{ $bucketBadge('90+') }}">90+: {{ number_format($supplierBucketTotal['90+'], 2) }}</span>
                </div>
            </div>
        </div>
    @endif

    @foreach($supplierAging as $aging)
        <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.25s;">
            <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/50">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ $aging['supplier']->name }}</span>
                    <div class="flex flex-wrap gap-1.5 items-center">
                        <span class="{{ $bucketBadge('0-30') }}">{{ number_format($aging['bucket_totals']['0-30'], 2) }}</span>
                        <span class="{{ $bucketBadge('31-60') }}">{{ number_format($aging['bucket_totals']['31-60'], 2) }}</span>
                        <span class="{{ $bucketBadge('61-90') }}">{{ number_format($aging['bucket_totals']['61-90'], 2) }}</span>
                        <span class="{{ $bucketBadge('90+') }}">{{ number_format($aging['bucket_totals']['90+'], 2) }}</span>
                        <span class="text-sm font-bold tabular-nums text-ink-900 dark:text-white ms-1">{{ number_format($aging['total_outstanding'], 2) }} {{ $currencySymbol }}</span>
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
                        <div class="text-end flex flex-col items-end gap-1">
                            <span class="text-sm font-semibold tabular-nums text-ink-900 dark:text-ink-100">{{ number_format($purchase['outstanding'], 2) }} {{ $currencySymbol }}</span>
                            <span class="{{ $bucketBadge($purchase['bucket']) }}">{{ $purchase['bucket'] }}</span>
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
