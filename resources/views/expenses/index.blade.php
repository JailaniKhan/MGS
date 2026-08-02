@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.expenses') }}</h2>
        <a href="{{ route('expenses.create') }}" class="btn-primary btn-sm">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_expense') }}
        </a>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        @forelse ($expenses as $expense)
            <a href="{{ route('expenses.show', $expense) }}" class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-danger-500 to-danger-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <x-icon name="currency-dollar" class="w-4 h-4 text-white"/>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $expense->category }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">
                            {{ $expense->expense_date->format('d M Y') }}
                            @if($expense->notes) &middot; {{ Str::limit($expense->notes, 25) }}@endif
                        </div>
                    </div>
                </div>
                <div class="text-sm font-bold text-danger-600 dark:text-danger-400 flex-shrink-0 ml-3">
                    -{{ number_format($expense->amount) }} {{ $expense->currency_symbol }}
                </div>
            </a>
        @empty
            <div class="empty-state">
                <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                    <x-icon name="currency-dollar" class="w-6 h-6 text-ink-400"/>
                </div>
                <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_expenses') }}</p>
            </div>
        @endforelse
    </div>

    @if($expenses->hasPages())
        <div class="mt-4">
            {{ $expenses->links() }}
        </div>
    @endif
@endsection