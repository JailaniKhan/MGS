@props([
    'id' => 'modal',
    'title' => null,
])

<div class="modal-backdrop" id="{{ $id }}-backdrop" data-modal-backdrop="{{ $id }}"></div>
<div class="modal-card" id="{{ $id }}" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    @if ($title)
        <h3 class="text-base font-extrabold text-ink-900 dark:text-white mb-1">{{ $title }}</h3>
    @endif
    <div class="modal-body">
        {{ $slot }}
    </div>
</div>
