@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.backup') }}</h2>
        <a href="{{ route('backup.create') }}" class="btn-primary btn-sm"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>{{ __('messages.new_backup') }}</a>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        @forelse ($backups as $backup)
            <div class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $backup['filename'] }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $backup['date'] }} &middot; {{ $backup['size'] }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0 ml-2">
                    <a href="{{ route('backup.download', $backup['filename']) }}" class="p-2 text-ink-400 hover:text-primary-500 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                    </a>
                    <form action="{{ route('backup.destroy', $backup['filename']) }}" method="POST" onsubmit="return confirm('{{ __('messages.are_you_sure') }}')">
                        @csrf @method('DELETE')
                        <button class="p-2 text-ink-400 hover:text-danger-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_backups') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="card p-4 bg-secondary-50 dark:bg-secondary-900/10 border-secondary-200 dark:border-secondary-800/30">
        <div class="text-xs font-bold text-secondary-800 dark:text-secondary-300 uppercase tracking-wider mb-2">{{ __('messages.backup_problems') }}</div>
        <ul class="text-[11px] text-secondary-700 dark:text-secondary-300 space-y-1">
            <li>• {{ __('messages.customers_dash') }} Customer data</li>
            <li>• {{ __('messages.suppliers_dash') }} Supplier data</li>
            <li>• {{ __('messages.products_dash') }} Product data</li>
            <li>• {{ __('messages.orders_dash') }} Order data</li>
            <li>• {{ __('messages.purchases_dash') }} Purchase data</li>
            <li>• {{ __('messages.payments_dash') }} Payment data</li>
            <li>• {{ __('messages.expenses_dash') }} Expense data</li>
            <li>• {{ __('messages.staff_dash') }} Employee data</li>
            <li>• {{ __('messages.others_dash') }} All other tables</li>
        </ul>
    </div>
@endsection