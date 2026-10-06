@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-brand/10 dark:bg-brand/20 border border-brand/20 dark:border-brand/30 flex items-center justify-center flex-shrink-0">
                <x-icon name="arrow-path" class="w-4 h-4 text-brand" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.restore_backup') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.restore_hint') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('backup.index') }}"/>
    </div>

    {{-- Pick the archive --}}
    <div class="card p-4 mb-4 page-enter" style="animation-delay: 0.05s;">
        @if ($isDevice)
            <div class="flex items-start gap-3 p-3.5 bg-accent-50 dark:bg-accent-900/20 border border-accent-200 dark:border-accent-800/30 rounded-xl mb-4">
                <div class="w-8 h-8 rounded-lg bg-accent-100 dark:bg-accent-900/40 flex items-center justify-center flex-shrink-0">
                    <x-icon name="device-phone-mobile" class="w-4 h-4 text-accent-600 dark:text-accent-400" strokeWidth="1.8"/>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-bold text-accent-800 dark:text-accent-300 uppercase tracking-wider mb-1">{{ __('messages.restore_from_phone') }}</div>
                    <p class="text-[11px] leading-relaxed text-accent-700 dark:text-accent-300">{{ __('messages.restore_pick_hint') }}</p>
                </div>
            </div>

            {{-- The WebView cannot upload a file, so the native picker reads it
                 off the phone and hands the bytes to PHP (Files.Pick). --}}
            <form action="{{ route('backup.restore.device') }}" method="POST">
                @csrf
                <button type="submit" class="btn-primary w-full">
                    <x-icon name="folder-open" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.restore_pick_file') }}
                </button>
            </form>
        @else
            <form action="{{ route('backup.restore.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="form-label">{{ __('messages.backup_file_name') }}</label>
                    <input type="file" name="archive" accept="application/json,.json" required class="form-input">
                    @error('archive') <p class="text-danger-500 text-[11px] mt-1.5">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn-primary w-full">
                    <x-icon name="arrow-path" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.restore_continue') }}
                </button>
            </form>
        @endif
    </div>

    {{-- What the next step does --}}
    <div class="card p-4 bg-secondary-50 dark:bg-secondary-900/10 border-secondary-200 dark:border-secondary-800/30 page-enter" style="animation-delay: 0.15s;">
        <div class="flex items-center gap-2 mb-2.5">
            <x-icon name="shield-check" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
            <span class="text-xs font-bold text-secondary-800 dark:text-secondary-300 uppercase tracking-wider">{{ __('messages.restore_how') }}</span>
        </div>
        <ul class="space-y-1.5">
            <li class="text-[11px] leading-relaxed text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.restore_step_pick') }}</li>
            <li class="text-[11px] leading-relaxed text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.restore_step_confirm') }}</li>
            <li class="text-[11px] leading-relaxed text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.restore_step_apply') }}</li>
            <li class="text-[11px] leading-relaxed text-secondary-700 dark:text-secondary-300">&bull; {{ __('messages.restore_keeps') }}</li>
        </ul>
    </div>
@endsection
