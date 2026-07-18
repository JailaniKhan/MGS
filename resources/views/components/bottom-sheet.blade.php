@props([
    'id' => 'bottom-sheet',
    'title' => null,
])

<div class="sheet-backdrop" id="{{ $id }}-backdrop" data-sheet-backdrop="{{ $id }}"></div>
<div class="bottom-sheet" id="{{ $id }}" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="sheet-handle"></div>
    @if ($title)
        <div class="px-1 mb-3">
            <h3 class="text-base font-extrabold text-ink-900 dark:text-white">{{ $title }}</h3>
        </div>
    @endif
    <div class="sheet-body">
        {{ $slot }}
    </div>
</div>
