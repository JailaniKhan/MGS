@props(['placeholder' => __('messages.search'), 'emptyText' => __('messages.no_results'), 'filterable' => true])

@php
    // The list target can be given via attribute (data-list-filter="...") or prop.
    // Route it to the <input> so the ui.js filter can find it on the right element.
    $filterTarget = $attributes->get('data-list-filter', 'filterable-list');
@endphp

<div {{ $attributes->except('data-list-filter')->merge(['class' => 'search-bar sticky top-[3.5rem] z-10 mb-3']) }}>
    <x-icon name="magnifying-glass" class="search-icon" strokeWidth="1.8"/>
    <input type="search" inputmode="search"
           data-list-filter="{{ $filterTarget }}"
           data-empty-text="{{ $emptyText }}"
           autocomplete="off"
           placeholder="{{ $placeholder }}"
           class="flex-1">
    {{ $slot }}
</div>
