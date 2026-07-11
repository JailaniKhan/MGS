@extends('layouts.app')

@section('content')
    <div class="mb-4 page-enter">
        <a href="{{ route('backup.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>{{ __('messages.back') }}
        </a>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-2">{{ __('messages.new_backup_creation') }}</h2>
    </div>

    <div class="card p-4 page-enter" style="animation-delay: 0.1s;">
        <form action="{{ route('backup.store') }}" method="POST">
            @csrf
            
            <div class="mb-4 p-3.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/30 rounded-xl">
                <div class="text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider mb-2">{{ __('messages.reminder') }}</div>
                <p class="text-[11px] text-amber-700 dark:text-amber-300">
                    {{ __('messages.backup_description') }} JSON {{ __('messages.stored_in_file') }}
                    {{ __('messages.backup_instruction') }}
                </p>
            </div>

            <div class="mb-4">
                <label class="form-label">{{ __('messages.backup_name') }}</label>
                <input type="text" disabled value="{{ __('messages.your_database') }}{{ date('Y/m/d H:i') }})" class="form-input bg-gray-100 dark:bg-gray-800 cursor-not-allowed">
            </div>

            <button type="submit" class="btn-primary w-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ __('messages.create_backup') }}</button>
        </form>
    </div>
@endsection