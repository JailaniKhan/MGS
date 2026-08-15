@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-4 page-enter">
    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.staff_book') }}</h2>
    <a href="{{ route('staff.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.staff_member') }}</a>
</div>

<div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
    @forelse ($employees as $employee)
        <a href="{{ route('staff.show', $employee) }}" class="list-row">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 flex items-center justify-center flex-shrink-0 border border-secondary-100 dark:border-secondary-800/40">
                    <span class="text-secondary-600 dark:text-secondary-300 font-bold text-sm">{{ substr($employee->name, 0, 1) }}</span>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $employee->name }}</div>
                    <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $employee->position }} | {{ number_format((float) $employee->monthly_salary, 2) }} {{ $employee->currency }}</div>
                </div>
            </div>
            <x-icon name="chevron-right" class="w-4 h-4 text-ink-400 flex-shrink-0" strokeWidth="2"/>
        </a>
    @empty
        <div class="empty-state">
            <div class="empty-illustration">
                <x-icon name="user" class="w-6 h-6 text-ink-400"/>
            </div>
            <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_staff') }}</p>
        </div>
    @endforelse
</div>
@if ($employees->hasPages())
    <div class="mt-4">{{ $employees->links() }}</div>
@endif
@endsection