@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.suppliers') }}</h2>
        <a href="{{ route('suppliers.create') }}" class="btn-primary btn-sm">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_supplier') }}
        </a>
    </div>

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;">
        @forelse ($suppliers as $supplier)
            <div class="swipe-row">
                <div class="swipe-content">
                    <div class="w-9 h-9 rounded-[0.875rem] bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center flex-shrink-0 border border-accent-100 dark:border-accent-800/40">
                        <span class="text-secondary-600 dark:text-secondary-300 font-bold text-sm">{{ substr($supplier->name, 0, 1) }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $supplier->name }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">
                            @if ($supplier->phone){{ $supplier->phone }}@endif
                            @if ($supplier->purchases_count) <span class="ml-1">{{ $supplier->purchases_count }} {{ __('messages.purchases') }}</span>@endif
                        </div>
                    </div>
                </div>
                <div class="swipe-actions">
                    @if ($supplier->phone)
                        <a href="{{ route('whatsapp.chats.show', ['supplier', $supplier->id]) }}" class="act-edit !bg-brand" aria-label="{{ __('messages.wa_open_chat') }}">
                            <x-icon name="chat-bubble-left-right" class="w-4 h-4"/>
                        </a>
                    @endif
                    <a href="{{ route('suppliers.edit', $supplier) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                        <x-icon name="pencil-square" class="w-4 h-4"/>
                    </a>
                    <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" onsubmit="return confirm('{{ __('messages.are_you_sure') }}')">
                        @csrf @method('DELETE')
                        <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                            <x-icon name="trash" class="w-4 h-4"/>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <x-empty-state title="{{ __('messages.no_suppliers') }}">
                <x-icon name="shopping-bag" class="w-6 h-6 text-ink-400"/>
            </x-empty-state>
        @endforelse
    </div>
    @if ($suppliers->hasPages())
        <div class="mt-4">{{ $suppliers->links() }}</div>
    @endif
@endsection