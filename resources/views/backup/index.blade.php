@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 border border-brand/20 dark:border-brand/30 flex items-center justify-center flex-shrink-0">
                <x-icon name="circle-stack" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.backup') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.backup_instruction') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-1.5 flex-shrink-0">
            <a href="{{ route('backup.restore') }}" class="btn-secondary btn-sm"><x-icon name="arrow-path" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.restore') }}</a>
            <a href="{{ route('backup.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>{{ __('messages.new_backup') }}</a>
        </div>
    </div>

    {{-- Backups --}}
    <div class="card overflow-hidden mb-4 page-enter" style="animation-delay: 0.05s;">
        @forelse ($backups as $backup)
            <div class="list-row">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-10 h-10 rounded-xl {{ $backup['is_json_only'] ? 'bg-accent-50 dark:bg-accent-900/25 text-accent-600 dark:text-accent-400' : 'bg-danger-50 dark:bg-danger-900/25 text-danger-500 dark:text-danger-400' }} flex items-center justify-center flex-shrink-0">
                        <x-icon name="document-text" class="w-4 h-4" strokeWidth="1.8"/>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-ink-800 dark:text-ink-200 truncate">{{ __('messages.backup') }} {{ substr($backup['filename'], 7, 4) }}-{{ substr($backup['filename'], 12, 2) }}-{{ substr($backup['filename'], 15, 2) }}</span>
                            {{-- One backup is a PDF + JSON pair; badge whichever of
                                 the two actually exists (legacy archives are
                                 JSON-only, so both cases are real). --}}
                            @if (! $backup['is_json_only'])
                                <span class="badge badge-danger">PDF</span>
                            @endif
                            @if ($backup['has_json'])
                                <span class="badge badge-warning">JSON</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400 tabular-nums" dir="ltr">{{ $backup['date'] }} &middot; {{ $backup['size'] }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0 ms-2">
                    @if (\App\Support\NativeDocument::available())
                        {{-- The JSON twin is what a restore reads back; on the phone it
                             has to be exported into Downloads/MGS to be pickable. --}}
                        <a href="{{ route('backup.json', $backup['json']) }}" aria-label="{{ __('messages.save_json') }}"
                           class="w-9 h-9 rounded-xl flex items-center justify-center text-ink-500 dark:text-ink-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 dark:hover:text-primary-400 transition-all duration-200 active:scale-95">
                            <x-icon name="document-arrow-down" class="w-4 h-4" strokeWidth="1.8"/>
                        </a>
                    @endif
                    @if(\App\Support\NativeDocument::available() && str_ends_with($backup['download'], '.pdf'))
                        <a href="{{ route('backup.share', $backup['download']) }}" aria-label="{{ __('messages.file_name') }}"
                           class="w-9 h-9 rounded-xl flex items-center justify-center text-ink-500 dark:text-ink-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 dark:hover:text-primary-400 transition-all duration-200 active:scale-95">
                            <x-icon name="arrow-up-tray" class="w-4 h-4" strokeWidth="1.8"/>
                        </a>
                    @else
                    <a href="{{ route('backup.download', $backup['download']) }}" aria-label="{{ __('messages.file_name') }}"
                       class="w-9 h-9 rounded-xl flex items-center justify-center text-ink-500 dark:text-ink-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 dark:hover:text-primary-400 transition-all duration-200 active:scale-95">
                        <x-icon name="arrow-down-tray" class="w-4 h-4" strokeWidth="1.8"/>
                    </a>
                    @endif
                    <form action="{{ route('backup.destroy', $backup['download']) }}" method="POST" onsubmit="return confirm('{{ __('messages.are_you_sure') }}')">
                        @csrf @method('DELETE')
                        <button class="w-9 h-9 rounded-xl flex items-center justify-center text-ink-400 dark:text-ink-500 hover:bg-danger-50 dark:hover:bg-danger-900/20 hover:text-danger-500 transition-all duration-200 active:scale-95" aria-label="{{ __('messages.delete') }}">
                            <x-icon name="trash" class="w-4 h-4" strokeWidth="1.8"/>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <x-empty-state title="{{ __('messages.no_backups') }}">
                <x-icon name="folder-open" class="w-6 h-6 text-ink-400"/>
            </x-empty-state>
        @endforelse
    </div>

    {{-- Coverage --}}
    <div class="card p-4 bg-secondary-50 dark:bg-secondary-900/10 border-secondary-200 dark:border-secondary-800/30 page-enter" style="animation-delay: 0.15s;">
        <div class="flex items-center gap-2 mb-2.5">
            <x-icon name="shield-check" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
            <span class="text-xs font-bold text-secondary-800 dark:text-secondary-300 uppercase tracking-wider">{{ __('messages.backup_problems') }}</span>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-1">
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.customers') }}</span>
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.suppliers') }}</span>
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.products') }}</span>
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.orders') }}</span>
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.purchases') }}</span>
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.payments') }}</span>
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.expenses') }}</span>
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.staff') }}</span>
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.cashbook_section') }}</span>
            <span class="text-[11px] text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.settings') }}</span>
        </div>
    </div>
@endsection
