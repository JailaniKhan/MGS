@props(['placeholder' => __('messages.search'), 'emptyText' => __('messages.no_results')])

<div class="search-bar sticky top-0 z-10 mb-3">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
    </svg>
    <input type="search" inputmode="search" data-empty-text="{{ $emptyText }}" {{ $attributes }} placeholder="{{ $placeholder }}">
    {{ $slot }}
</div>
