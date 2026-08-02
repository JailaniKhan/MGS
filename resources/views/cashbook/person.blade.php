@extends('layouts.app')

@section('content')
    <a href="{{ route('cashbook.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" strokeWidth="1.8"/>
        {{ __('messages.back_to_cashbook') }}
    </a>

    <div class="flex items-center gap-3 mb-4 page-enter">
        <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center flex-shrink-0 shadow-sm">
            <span class="text-white font-bold text-base">{{ substr($person->name, 0, 1) }}</span>
        </div>
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-ink-900 dark:text-white truncate">{{ $person->name }}</h2>
            <span class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-ink-100 dark:bg-ink-800 text-ink-600 dark:text-ink-300 capitalize">{{ $personType }}</span>
        </div>
    </div>

    @foreach ($totals as $currency => $total)
        <div class="grid grid-cols-3 gap-3 mb-4 page-enter" style="animation-delay: 0.05s;">
            <div class="metric-tile">
                <span class="metric-label">{{ __('messages.cashbook_in_total') }}</span>
                <span class="metric-value text-primary-600 dark:text-primary-400">{{ number_format($total['in'], 2) }} {{ $currency }}</span>
            </div>
            <div class="metric-tile">
                <span class="metric-label">{{ __('messages.cashbook_out_total') }}</span>
                <span class="metric-value text-danger-600 dark:text-danger-400">{{ number_format($total['out'], 2) }} {{ $currency }}</span>
            </div>
            <div class="metric-tile">
                <span class="metric-label">{{ __('messages.net_balance') }}</span>
                <span class="metric-value {{ $total['net'] >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                    {{ $total['net'] >= 0 ? '+' : '-' }}{{ number_format(abs($total['net']), 2) }} {{ $currency }}
                </span>
            </div>
        </div>
    @endforeach

    <div class="grid grid-cols-2 gap-3 mb-4 page-enter" style="animation-delay: 0.05s;">
        @foreach ($orderTotals as $currency => $amount)
            <div class="metric-tile">
                <span class="metric-label">{{ __('messages.total_orders') }}</span>
                <span class="metric-value text-ink-900 dark:text-ink-100">{{ number_format($amount, 2) }} {{ $currency }}</span>
            </div>
        @endforeach
        @foreach ($purchaseTotals as $currency => $amount)
            <div class="metric-tile">
                <span class="metric-label">{{ __('messages.total_purchases') }}</span>
                <span class="metric-value text-ink-900 dark:text-ink-100">{{ number_format($amount, 2) }} {{ $currency }}</span>
            </div>
        @endforeach
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        @forelse ($transactions as $tx)
            <div class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl {{ $tx->direction === 'in' ? 'bg-primary-500 text-white' : 'bg-danger-500 text-white' }} flex items-center justify-center flex-shrink-0 shadow-sm">
                        @if($tx->kind === 'order')
                            <x-icon name="document-text" class="w-4 h-4 text-white"/>
                        @elseif($tx->kind === 'purchase')
                            <x-icon name="shopping-bag" class="w-4 h-4 text-white"/>
                        @else
                            @if($tx->direction === 'in')
                                <x-icon name="plus" class="w-4 h-4 text-white"/>
                            @else
                                <x-icon name="minus" class="w-4 h-4 text-white"/>
                            @endif
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">
                            {{ $tx->label }}
                        </div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">
                            {{ $tx->date->format('d M Y') }} &middot; {{ $tx->notes }}
                        </div>
                    </div>
                </div>
                <div class="text-right flex-shrink-0 ml-3">
                    <div class="text-sm font-bold {{ $tx->direction === 'in' ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $tx->direction === 'in' ? '+' : '-' }}{{ number_format((float) $tx->amount, 2) }}
                    </div>
                    <div class="text-[10px] text-ink-400">{{ $tx->currency }}</div>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                    <x-icon name="currency-dollar" class="w-6 h-6 text-ink-400"/>
                </div>
                <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_person_transactions') }}</p>
            </div>
        @endforelse
    </div>
@endsection
