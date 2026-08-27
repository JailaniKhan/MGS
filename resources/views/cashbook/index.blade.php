@extends('layouts.app')

@php
    $tileStyles = [
        'bg-primary-500/10 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400',
        'bg-secondary-500/10 dark:bg-secondary-500/15 text-secondary-600 dark:text-secondary-400',
        'bg-accent-500/10 dark:bg-accent-500/15 text-accent-600 dark:text-accent-400',
    ];
@endphp

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-primary-500/10 dark:bg-primary-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="wallet" class="w-4 h-4 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.cashbook') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.by_person') }}</p>
            </div>
        </div>
        <a href="{{ route('cashbook.create') }}" class="btn-primary btn-sm flex-shrink-0">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_entry') }}
        </a>
    </div>

    @if($cashUnbalanced ?? false)
        <div class="card mb-4 border border-danger-500/40 bg-danger-500/5 page-enter" style="animation-delay: 0.05s;">
            <div class="flex items-start gap-2 p-3">
                <x-icon name="exclamation-triangle" class="w-4 h-4 text-danger-500 flex-shrink-0 mt-0.5" strokeWidth="1.8"/>
                <p class="text-xs font-medium text-danger-600 dark:text-danger-400 leading-relaxed">{{ __('messages.cashbook_unbalanced') }}</p>
            </div>
        </div>
    @endif

    {{-- Cash totals --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-primary-500/[0.08] dark:bg-primary-400/[0.08]"></div>
        <div class="relative">
            <div class="flex items-center gap-1.5">
                <x-icon name="banknotes" class="w-3.5 h-3.5 text-ink-400 dark:text-ink-500" strokeWidth="1.8"/>
                <span class="metric-label">{{ __('messages.cashbook') }}</span>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-3">
                <div>
                    <p class="text-[10px] font-bold text-ink-400 dark:text-ink-500 mb-0.5">{{ __('messages.afn') }}</p>
                    <p class="text-xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-white" dir="ltr">{{ number_format((float) $cashAFN, 2) }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-ink-400 dark:text-ink-500 mb-0.5">$</p>
                    <p class="text-xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-white" dir="ltr">{{ number_format((float) $cashUSD, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 gap-2 mb-4 page-enter" style="animation-delay: 0.08s;">
        <div class="card !p-3">
            <div class="w-6 h-6 rounded-lg bg-primary-500/10 dark:bg-primary-500/15 flex items-center justify-center mb-2">
                <x-icon name="arrow-down-tray" class="w-3.5 h-3.5 text-primary-600 dark:text-primary-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.cash_afn') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums {{ (float) $cashAFN >= 0 ? 'text-ink-800 dark:text-ink-100' : 'text-danger-600 dark:text-danger-400' }}">{{ number_format((float) $cashAFN) }}</p>
        </div>
        <div class="card !p-3">
            <div class="w-6 h-6 rounded-lg bg-secondary-500/10 dark:bg-secondary-500/15 flex items-center justify-center mb-2">
                <x-icon name="currency-dollar" class="w-3.5 h-3.5 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.cash_usd') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums {{ (float) $cashUSD >= 0 ? 'text-ink-800 dark:text-ink-100' : 'text-danger-600 dark:text-danger-400' }}">{{ number_format((float) $cashUSD) }}</p>
        </div>
    </div>

    {{-- Search --}}
    <div class="search-bar sticky top-[3.5rem] z-10 mb-3 page-enter" style="animation-delay: 0.1s;">
        <x-icon name="magnifying-glass" class="search-icon" strokeWidth="1.8"/>
        <input type="search" inputmode="search"
               data-list-filter="cashbook-people"
               data-empty-text="{{ __('messages.no_results') }}"
               autocomplete="off"
               placeholder="{{ __('messages.search') }}"
               class="flex-1">
    </div>

    {{-- People --}}
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.13s;" id="cashbook-people">
        @forelse ($people as $person)
            @php
                $netAFN = $person['in']['AFN'] - $person['out']['AFN'];
                $netUSD = $person['in']['USD'] - $person['out']['USD'];
                $tile = $tileStyles[crc32($person['name']) % count($tileStyles)];
            @endphp
            <a href="{{ route('cashbook.person', [$person['type'], $person['id']]) }}" class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl {{ $tile }} flex items-center justify-center flex-shrink-0">
                        <span class="font-bold text-sm">{{ mb_substr($person['name'], 0, 1) }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $person['name'] }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400 truncate">
                            {{ $person['count'] }} {{ __('messages.entries') }} &middot; <span class="capitalize">{{ $person['type'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-end flex-shrink-0 ms-3">
                    @if($netAFN != 0)
                        <div class="text-sm font-extrabold tabular-nums {{ $netAFN >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ $netAFN >= 0 ? '+' : '-' }}{{ number_format(abs($netAFN)) }} <span class="text-[10px] font-medium text-ink-400">AFN</span>
                        </div>
                    @endif
                    @if($netUSD != 0)
                        <div class="text-sm font-extrabold tabular-nums {{ $netUSD >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ $netUSD >= 0 ? '+' : '-' }}{{ number_format(abs($netUSD)) }} <span class="text-[10px] font-medium text-ink-400">USD</span>
                        </div>
                    @endif
                    @if($netAFN == 0 && $netUSD == 0)
                        <div class="text-sm font-bold text-ink-400">&mdash;</div>
                    @endif
                </div>
            </a>
        @empty
            <x-empty-state title="{{ __('messages.no_entries') }}">
                <x-icon name="wallet" class="w-6 h-6 text-ink-400"/>
            </x-empty-state>
        @endforelse
    </div>
    @if ($people->hasPages())
        <div class="mt-3 mb-4">{{ $people->links() }}</div>
    @endif
@endsection
