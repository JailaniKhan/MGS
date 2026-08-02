@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-4 page-enter">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ __('messages.customers') }}</h2>
        <a href="{{ route('customers.create') }}" class="btn-primary btn-sm">
            <x-icon name="plus" class="w-3.5 h-3.5" strokeWidth="2"/>
            {{ __('messages.new_customer') }}
        </a>
    </div>

    <x-search-filter-bar id="customers-search" data-list-filter="customers-list" />

    <div class="card overflow-hidden page-enter" style="animation-delay: 0.1s;" id="customers-list">
        @forelse ($customers as $customer)
            <div class="swipe-row">
                <a href="{{ route('customers.show', $customer) }}" class="swipe-content">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-secondary-500 to-secondary-700 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <span class="text-white font-bold text-sm">{{ substr($customer->name, 0, 1) }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $customer->name }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">
                            @if ($customer->phone)<span>{{ $customer->phone }}</span>@endif
                            @if ($customer->orders_count)<span class="ml-2">{{ $customer->orders_count }} {{ __('messages.orders') }}</span>@endif
                        </div>
                    </div>
                </a>
                <div class="swipe-actions">
                    <a href="{{ route('customers.edit', $customer) }}" class="act-edit" aria-label="{{ __('messages.edit') }}">
                        <x-icon name="pencil-square" class="w-4 h-4"/>
                    </a>
                    <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('{{ __('messages.are_you_sure') }}')">
                        @csrf @method('DELETE')
                        <button class="act-danger" aria-label="{{ __('messages.delete') }}">
                            <x-icon name="trash" class="w-4 h-4"/>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="w-12 h-12 rounded-2xl bg-ink-100 dark:bg-ink-800 flex items-center justify-center mb-3">
                    <x-icon name="users" class="w-6 h-6 text-ink-400"/>
                </div>
                <p class="text-sm font-medium text-ink-500 dark:text-ink-400">{{ __('messages.no_customers') }}</p>
            </div>
        @endforelse
    </div>
    @if ($customers->hasPages())
        <div class="mt-4">{{ $customers->links() }}</div>
    @endif
@endsection