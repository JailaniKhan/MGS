@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4 page-enter">
    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.staff_book') }}</h2>
    <a href="{{ route('staff.create') }}" class="btn-primary btn-sm"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>{{ __('messages.staff_member') }}</a>
</div>

<div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
    @forelse ($employees as $employee)
        <a href="{{ route('staff.show', $employee) }}" class="list-row">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-secondary-500 to-secondary-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                    <span class="text-white font-bold text-sm">{{ substr($employee->name, 0, 1) }}</span>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $employee->name }}</div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $employee->position }} | {{ number_format((float) $employee->monthly_salary, 2) }} {{ $employee->currency }}</div>
                </div>
            </div>
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
            </svg>
        </a>
    @empty
        <div class="empty-state">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('messages.no_staff') }}</p>
        </div>
    @endforelse
</div>
@endsection