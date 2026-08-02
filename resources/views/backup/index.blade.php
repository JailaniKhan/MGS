@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.backup') }}</h2>
        <a href="{{ route('backup.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.new_backup') }}</a>
    </div>

    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.1s;">
        @forelse ($backups as $backup)
            <div class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <x-icon name="folder-open" class="w-4 h-4 text-white"/>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $backup['filename'] }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $backup['date'] }} &middot; {{ $backup['size'] }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0 ml-2">
                    <a href="{{ route('backup.download', $backup['filename']) }}" class="p-2 text-ink-400 hover:text-primary-500 transition-colors">
                        <x-icon name="arrow-down-tray" class="w-4 h-4"/>
                    </a>
                    <form action="{{ route('backup.destroy', $backup['filename']) }}" method="POST" onsubmit="return confirm('{{ __('messages.are_you_sure') }}')">
                        @csrf @method('DELETE')
                        <button class="p-2 text-ink-400 hover:text-danger-500 transition-colors">
                            <x-icon name="trash" class="w-4 h-4"/>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                    <x-icon name="folder-open" class="w-6 h-6 text-ink-400"/>
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