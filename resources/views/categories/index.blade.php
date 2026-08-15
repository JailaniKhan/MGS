@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.category') }}</h2>
        <a href="{{ route('categories.create') }}" class="btn-primary btn-sm">
            <x-icon name="plus" class="w-3.5 h-3.5"/>
            {{ __('messages.new_category') }}
        </a>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        @forelse ($categories as $category)
            <div class="swipe-row">
                <div class="swipe-content">
                    <div class="w-9 h-9 rounded-[0.875rem] bg-secondary-50 dark:bg-secondary-900/30 flex items-center justify-center flex-shrink-0 border border-secondary-100 dark:border-secondary-800/40">
                        <x-icon name="tag" class="w-4 h-4 text-white"/>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $category->name }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ $category->products_count }} {{ __('messages.products') }}</div>
                    </div>
                </div>
                <div class="swipe-actions">
                    <a href="{{ route('categories.edit', $category) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                        <x-icon name="pencil" class="w-4 h-4"/>
                    </a>
                    <form action="{{ route('categories.destroy', $category) }}" method="POST" onsubmit="return confirm('{{ __('messages.are_you_sure') }}')">
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
                    <x-icon name="tag" class="w-6 h-6 text-ink-400"/>
                </div>
                <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_categories') }}</p>
            </div>
        @endforelse
    </div>
    @if ($categories->hasPages())
        <div class="mt-4">{{ $categories->links() }}</div>
    @endif
@endsection