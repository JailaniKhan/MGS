@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('backup.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
            <x-icon name="arrow-left" class="w-3.5 h-3.5"/>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mt-2">{{ __('messages.new_backup_creation') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('backup.store') }}" method="POST">
            @csrf
            
            <div class="mb-4 p-3.5 bg-accent-50 dark:bg-accent-900/20 border border-accent-200 dark:border-accent-800/30 rounded-xl">
                <div class="text-xs font-bold text-accent-800 dark:text-accent-300 uppercase tracking-wider mb-2">{{ __('messages.reminder') }}</div>
                <p class="text-[11px] text-accent-700 dark:text-accent-300">
                    {{ __('messages.backup_description') }} JSON {{ __('messages.stored_in_file') }}
                    {{ __('messages.backup_instruction') }}
                </p>
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.backup_name') }}</label>
                <input type="text" disabled value="{{ __('messages.your_database') }}{{ date('Y/m/d H:i') }})" class="form-input bg-ink-100 dark:bg-ink-800 cursor-not-allowed">
            </div>

            <button type="submit" class="btn-primary w-full"><x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>{{ __('messages.create_backup') }}</button>
        </form>
    </div>
@endsection