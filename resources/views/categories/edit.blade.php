@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4 page-enter">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-500/10 dark:bg-secondary-500/15 flex items-center justify-center flex-shrink-0">
                <x-icon name="tag" class="w-4 h-4 text-secondary-600 dark:text-secondary-400" strokeWidth="1.8"/>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white leading-tight">{{ __('messages.edit_category') }}</h2>
                <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">{{ __('messages.inventory') }}</p>
            </div>
        </div>
        <x-back-button href="{{ route('inventory.index') }}"/>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        <div class="px-4 py-3 border-b border-ink-100 dark:border-ink-700/30 bg-ink-50 dark:bg-ink-800/40">
            <div class="flex items-center gap-2">
                <x-icon name="tag" class="w-4 h-4 text-secondary-600 dark:text-secondary-400"/>
                <h3 class="text-sm font-semibold text-ink-800 dark:text-ink-200">{{ __('messages.details') }}</h3>
            </div>
        </div>
        <form action="{{ route('categories.update', $category) }}" method="POST" class="p-4">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="form-label">{{ __('messages.name') }}</label>
                <div class="relative">
                    <input type="text" name="name" value="{{ old('name', $category->name) }}" required class="form-input ps-10">
                    <x-icon name="tag" class="w-5 h-5 text-ink-400 dark:text-ink-500 pointer-events-none absolute top-1/2 -translate-y-1/2 start-3"/>
                </div>
                @error('name') <p class="text-danger-500 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full">
                <x-icon name="check-circle" class="w-4 h-4" strokeWidth="2"/>
                {{ __('messages.save') }}
            </button>
        </form>
    </div>
@endsection
