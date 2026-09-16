@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 border border-brand/20 dark:border-brand/30 flex items-center justify-center flex-shrink-0">
                <x-icon name="plus-circle" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.new_backup_creation') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.backup_instruction') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('backup.index') }}"/>
    </div>

    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        <form action="{{ route('backup.store') }}" method="POST">
            @csrf

            <div class="flex items-start gap-3 p-3.5 bg-accent-50 dark:bg-accent-900/20 border border-accent-200 dark:border-accent-800/30 rounded-xl mb-4">
                <div class="w-8 h-8 rounded-lg bg-accent-100 dark:bg-accent-900/40 flex items-center justify-center flex-shrink-0">
                    <x-icon name="document-text" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-bold text-accent-800 dark:text-accent-300 uppercase tracking-wider mb-1">PDF</div>
                    <p class="text-[11px] leading-relaxed text-accent-700 dark:text-accent-300">
                        {{ __('messages.backup_pdf_note') }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-4">
                @foreach ([
                    ['icon' => 'user-group', 'label' => __('messages.customers'), 'value' => $counts['customers']],
                    ['icon' => 'truck', 'label' => __('messages.suppliers'), 'value' => $counts['suppliers']],
                    ['icon' => 'cube', 'label' => __('messages.products'), 'value' => $counts['products']],
                    ['icon' => 'shopping-cart', 'label' => __('messages.orders'), 'value' => $counts['orders']],
                    ['icon' => 'shopping-bag', 'label' => __('messages.purchases'), 'value' => $counts['purchases']],
                    ['icon' => 'banknotes', 'label' => __('messages.payments'), 'value' => $counts['payments']],
                    ['icon' => 'credit-card', 'label' => __('messages.expenses'), 'value' => $counts['expenses']],
                    ['icon' => 'users', 'label' => __('messages.staff'), 'value' => $counts['staff']],
                    ['icon' => 'wallet', 'label' => __('messages.cashbook'), 'value' => $counts['cashbook']],
                ] as $tile)
                    <div class="card !p-3 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-brand/10 dark:bg-brand/20 flex items-center justify-center flex-shrink-0">
                            <x-icon :name="$tile['icon']" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] text-ink-400 dark:text-ink-500 truncate">{{ $tile['label'] }}</p>
                            <p class="text-sm font-extrabold tabular-nums text-ink-800 dark:text-ink-200" dir="ltr">{{ number_format($tile['value']) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.backup_name') }}</label>
                <div class="relative" dir="ltr">
                    <x-icon name="document-text" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                    <input type="text" disabled value="backup_{{ date('Y_m_d_H_i_s') }}.pdf" class="form-input ps-10 bg-ink-50 dark:bg-white/[0.03] cursor-not-allowed" dir="ltr">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.create_backup') }}</button>
        </form>
    </div>
@endsection
