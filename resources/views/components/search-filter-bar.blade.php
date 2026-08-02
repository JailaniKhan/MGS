@props(['placeholder' => __('messages.search'), 'emptyText' => __('messages.no_results'), 'filterable' => true])

<div {{ $attributes->merge(['class' => 'search-bar sticky top-0 z-10 mb-3']) }}>
    <x-icon name="magnifying-glass" class="search-icon" strokeWidth="1.8"/>
    <input type="search" inputmode="search"
           data-list-filter="filterable-list"
           data-empty-text="{{ $emptyText }}"
           autocomplete="off"
           placeholder="{{ $placeholder }}"
           class="flex-1">
    {{ $slot }}
</div>
