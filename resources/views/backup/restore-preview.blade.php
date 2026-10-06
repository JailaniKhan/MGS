@extends('layouts.app')

@section('content')
@php
    // Same wording the backup PDF uses for each section, so a shop recognises
    // the file it is putting back.
    $labels = [
        'customers' => __('messages.customers'),
        'suppliers' => __('messages.suppliers'),
        'categories' => __('messages.categories'),
        'units' => __('messages.units'),
        'products' => __('messages.products'),
        'orders' => __('messages.orders'),
        'order_items' => __('messages.order_items'),
        'payments' => __('messages.payments'),
        'order_returns' => __('messages.order_returns'),
        'purchases' => __('messages.purchases'),
        'purchase_items' => __('messages.purchase_items'),
        'purchase_payments' => __('messages.purchase_payments'),
        'purchase_returns' => __('messages.purchase_returns'),
        'party_payments' => __('messages.ledger'),
        'expenses' => __('messages.expenses'),
        'employees' => __('messages.staff'),
        'salary_payments' => __('messages.salaries'),
        'cashbook_entries' => __('messages.cashbook_section'),
        'accounts' => __('messages.accounts'),
        'journal_entries' => __('messages.journal_entries'),
        'ledger_entries' => __('messages.ledger_lines'),
        'stock_movements' => __('messages.stock_movements'),
        'audit_logs' => __('messages.audit_logs'),
        'settings' => __('messages.settings'),
    ];
@endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 border border-brand/20 dark:border-brand/30 flex items-center justify-center flex-shrink-0">
                <x-icon name="shield-check" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.restore_review') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.restore_review_hint') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('backup.restore') }}"/>
    </div>

    {{-- The picked file --}}
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-brand/10 dark:bg-brand/20 flex items-center justify-center flex-shrink-0">
                <x-icon name="document-text" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-semibold text-ink-800 dark:text-ink-200 truncate" dir="ltr">{{ $filename }}</div>
                <div class="text-[11px] text-ink-500 dark:text-ink-400 tabular-nums" dir="ltr">
                    {{ $preview['generated_at'] ?: __('messages.restore_unknown_date') }}
                    &middot; {{ __('messages.restore_records', ['count' => number_format($preview['total'])]) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Contents --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 pt-4 pb-2 text-xs font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wider">
            {{ __('messages.restore_whats_inside') }}
        </div>
        @forelse ($preview['counts'] as $table => $count)
            <div class="list-row">
                <span class="flex-1 min-w-0 text-sm text-ink-700 dark:text-ink-300 truncate">{{ $labels[$table] ?? $table }}</span>
                <span class="text-sm font-bold tabular-nums text-ink-800 dark:text-ink-200" dir="ltr">{{ number_format($count) }}</span>
            </div>
        @empty
            <x-empty-state title="{{ __('messages.restore_empty') }}">
                <x-icon name="folder-open" class="w-6 h-6 text-ink-400"/>
            </x-empty-state>
        @endforelse
    </div>

    {{-- How to apply it --}}
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.15s;">
        <form action="{{ route('backup.restore.apply') }}" method="POST"
              onsubmit="return confirm('{{ __('messages.restore_confirm') }}')">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <label class="form-label">{{ __('messages.restore_mode') }}</label>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <x-radio-pill name="mode" value="replace" :label="__('messages.restore_mode_replace')"
                              :checked="old('mode', 'replace') === 'replace'" pill/>
                <x-radio-pill name="mode" value="merge" :label="__('messages.restore_mode_merge')"
                              :checked="old('mode') === 'merge'" pill/>
            </div>
            <p class="text-[11px] leading-relaxed text-ink-500 dark:text-ink-400 mb-4">{{ __('messages.restore_mode_hint') }}</p>
            @error('mode') <p class="text-danger-500 text-[11px] -mt-2 mb-4">{{ $message }}</p> @enderror

            <button type="submit" class="btn-primary w-full">
                <x-icon name="arrow-path" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.restore_apply') }}
            </button>
        </form>
    </div>

    {{-- The blunt warning: replace is not undoable --}}
    <div class="card p-4 bg-danger-50 dark:bg-danger-900/20 border-danger-200 dark:border-danger-800/30 page-enter" style="animation-delay: 0.2s;">
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-danger-100 dark:bg-danger-900/40 flex items-center justify-center flex-shrink-0">
                <x-icon name="exclamation-triangle" class="w-4 h-4 text-danger-600 dark:text-danger-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <div class="text-xs font-bold text-danger-800 dark:text-danger-300 uppercase tracking-wider mb-1">{{ __('messages.restore_warning_title') }}</div>
                <p class="text-[11px] leading-relaxed text-danger-700 dark:text-danger-300">{{ __('messages.restore_warning') }}</p>
            </div>
        </div>
    </div>
@endsection
