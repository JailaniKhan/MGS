@extends('layouts.app')

@php
    $tileStyles = [
        'bg-secondary-500/10 dark:bg-secondary-500/15 text-secondary-600 dark:text-secondary-400',
        'bg-primary-500/10 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400',
        'bg-accent-500/10 dark:bg-accent-500/15 text-accent-600 dark:text-accent-400',
    ];
@endphp

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-500/10 dark:bg-secondary-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="user-group" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.staff_book') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ $total }} {{ __('messages.staff_member') }}</p>
            </div>
        </div>
        <a href="{{ route('staff.create') }}" class="btn-primary btn-sm flex-shrink-0">
            <x-icon name="user-plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_staff') }}
        </a>
    </div>

    {{-- Payroll summary --}}
    <div class="card relative overflow-hidden p-4 mb-3 page-enter" style="animation-delay: 0.05s;">
        <div class="pointer-events-none absolute -end-8 -top-10 w-32 h-32 rounded-full bg-secondary-500/[0.08] dark:bg-secondary-400/[0.08]"></div>
        <div class="relative">
            <div class="flex items-center gap-1.5">
                <x-icon name="banknotes" class="w-3.5 h-3.5 text-ink-400 dark:text-ink-500" strokeWidth="1.8"/>
                <span class="metric-label">{{ __('messages.monthly_salary') }}</span>
            </div>
            <p class="mt-1 text-3xl font-extrabold tabular-nums tracking-tight text-ink-900 dark:text-white">
                <span class="whitespace-nowrap"><bdi>{{ number_format($payrollAFN) }}</bdi> <span class="text-sm font-bold text-ink-400 dark:text-ink-500">{{ __('messages.afn') }}</span></span>
                @if ($payrollUSD > 0)
                    <span class="text-base font-bold text-ink-300 dark:text-ink-600 mx-1">&middot;</span>
                    <bdi class="whitespace-nowrap">{{ number_format($payrollUSD) }}$</bdi>
                @endif
            </p>
            <p class="mt-2 flex items-center gap-1.5 text-[11px] font-medium text-ink-500 dark:text-ink-400">
                <x-icon name="identification" class="w-3.5 h-3.5 text-ink-400" strokeWidth="1.8"/>
                {{ $total }} {{ __('messages.staff_member') }}
            </p>
        </div>
    </div>

    {{-- Payroll stat tiles --}}
    <div class="grid grid-cols-2 gap-2 mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="card !p-3">
            <div class="w-6 h-6 rounded-lg bg-secondary-500/10 dark:bg-secondary-500/15 flex items-center justify-center mb-2">
                <x-icon name="users" class="w-3.5 h-3.5 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.staff_member') }}</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums text-ink-800 dark:text-ink-100">{{ $total }}</p>
        </div>
        <div class="card !p-3">
            <div class="w-6 h-6 rounded-lg bg-accent-500/10 dark:bg-accent-500/15 flex items-center justify-center mb-2">
                <x-icon name="banknotes" class="w-3.5 h-3.5 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
            </div>
            <span class="metric-label !text-[9px]">{{ __('messages.afn') }} / $</span>
            <p class="mt-0.5 text-lg font-extrabold tabular-nums text-ink-800 dark:text-ink-100"><bdi>{{ number_format($payrollAFN) }}</bdi> / <bdi>{{ number_format($payrollUSD) }}</bdi></p>
        </div>
    </div>

    {{-- Search --}}
    <div class="search-bar sticky top-[3.5rem] z-10 mb-3 page-enter" style="animation-delay: 0.13s;">
        <x-icon name="magnifying-glass" class="search-icon" strokeWidth="1.8"/>
        <input type="search" inputmode="search"
               data-list-filter="staff-list"
               data-empty-text="{{ __('messages.no_results') }}"
               autocomplete="off"
               placeholder="{{ __('messages.search') }}"
               class="flex-1">
    </div>

    {{-- List --}}
    <div class="card overflow-hidden page-enter" style="animation-delay: 0.15s;" id="staff-list">
        @forelse ($employees as $employee)
            @php $tile = $tileStyles[crc32($employee->name) % count($tileStyles)]; @endphp
            <a href="{{ route('staff.show', $employee) }}" class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl {{ $tile }} flex items-center justify-center flex-shrink-0">
                        <span class="font-bold text-sm">{{ mb_substr($employee->name, 0, 1) }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $employee->name }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400 truncate">
                            {{ $employee->position ?: __('messages.position') }} &middot; <x-money :amount="$employee->monthly_salary" :currency="$employee->currency" symbol-class="text-[10px] font-medium text-ink-500"/>
                        </div>
                    </div>
                </div>
                <x-icon name="chevron-right" class="w-4 h-4 text-ink-400 flex-shrink-0" strokeWidth="2"/>
            </a>
        @empty
            <x-empty-state title="{{ __('messages.no_staff') }}">
                <x-icon name="user-group" class="w-6 h-6 text-ink-400"/>
            </x-empty-state>
        @endforelse
    </div>

    @if ($employees->hasPages())
        <div class="mt-4">{{ $employees->links() }}</div>
    @endif
@endsection
