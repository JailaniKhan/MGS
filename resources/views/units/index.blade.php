@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.units') }}</h2>
        <a href="{{ route('units.create') }}" class="btn-primary btn-sm">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_unit') }}
        </a>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        @forelse ($units as $unit)
            <div class="swipe-row">
                <div class="swipe-content">
                    <div class="w-9 h-9 rounded-[0.875rem] bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center flex-shrink-0 border border-accent-100 dark:border-accent-800/40">
                        <x-icon name="bars-3" class="w-4 h-4 text-white"/>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">
                            {{ $unit->name }}
                            @if ($unit->short_name)<span class="text-xs text-ink-400">({{ $unit->short_name }})</span>@endif
                        </div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $unit->products_count }} {{ __('messages.products') }}</div>
                    </div>
                </div>
                <div class="swipe-actions">
                    <a href="{{ route('units.edit', $unit) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                        <x-icon name="pencil-square" class="w-4 h-4"/>
                    </a>
                    <form action="{{ route('units.destroy', $unit) }}" method="POST" onsubmit="return confirm('{{ __('messages.are_you_sure') }}')">
                        @csrf @method('DELETE')
                        <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                            <x-icon name="trash" class="w-4 h-4"/>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="empty-illustration">
                    <x-icon name="bars-3" class="w-6 h-6 text-ink-400"/>
                </div>
                <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_units') }}</p>
            </div>
        @endforelse
    </div>
    @if ($units->hasPages())
        <div class="mt-4">{{ $units->links() }}</div>
    @endif
@endsection