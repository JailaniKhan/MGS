@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('expenses.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-danger-500 to-danger-700 flex items-center justify-center shadow-sm">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ $expense->category }}</h2>
                    <p class="text-xs text-ink-500 dark:text-ink-400">{{ $expense->expense_date->format('Y/m/d') }}</p>
                </div>
            </div>
            <span class="text-sm font-bold text-danger-600 dark:text-danger-400">{{ number_format($expense->amount) }} {{ $expense->currency_symbol }}</span>
        </div>

        @if ($expense->notes)
            <div class="text-xs text-ink-600 dark:text-ink-400 mb-3 bg-ink-50 dark:bg-ink-800/50 rounded-lg p-3">{{ $expense->notes }}</div>
        @endif

        <div class="text-xs text-ink-500 dark:text-ink-400 mb-3">{{ __('messages.currency_unit') }}: <span class="font-medium text-ink-700 dark:text-ink-300">{{ $expense->currency === 'USD' ? __('messages.usd_with_paren') . '$)' : __('messages.afn') . ' (' . __('messages.afn') . ')' }}</span></div>

        <div class="flex gap-2">
            <a href="{{ route('expenses.edit', $expense) }}" class="btn-sm">{{ __('messages.edit') }}</a>
            <form action="{{ route('expenses.destroy', $expense) }}" method="POST" onsubmit="return confirm('{{ __('messages.are_you_sure') }}')">
                @csrf @method('DELETE')
                <button class="btn-danger btn-sm">{{ __('messages.delete') }}</button>
            </form>
        </div>
    </div>
@endsection