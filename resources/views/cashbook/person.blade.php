@extends('layouts.app')

@section('content')
    <a href="{{ route('cashbook.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
        </svg>
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
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            @if($tx->kind === 'order')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            @elseif($tx->kind === 'purchase')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                            @else
                                @if($tx->direction === 'in')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15"/>
                                @endif
                            @endif
                        </svg>
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
                    <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_person_transactions') }}</p>
            </div>
        @endforelse
    </div>
@endsection
