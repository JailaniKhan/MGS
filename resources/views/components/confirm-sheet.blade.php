@props([
    'id' => 'confirm-sheet',
    'title' => null,
    'message' => null,
    'confirmLabel' => null,
    'cancelLabel' => null,
    'danger' => false,
])

<div class="modal-backdrop" id="{{ $id }}-backdrop" data-modal-backdrop="{{ $id }}"></div>
<div class="modal-card" id="{{ $id }}" role="alertdialog" aria-modal="true" aria-label="{{ $title }}">
    @if ($title)
        <h3 class="text-base font-extrabold text-ink-900 dark:text-white mb-1">{{ $title }}</h3>
    @endif
    @if ($message)
        <p class="text-[12px] text-ink-500 dark:text-ink-400 mb-4">{{ $message }}</p>
    @endif
    <div class="flex gap-2">
        <button type="button" class="btn-secondary flex-1" data-modal-cancel="{{ $id }}">
            {{ $cancelLabel ?? __('messages.cancel') }}
        </button>
        <button type="button" class="btn-{{ $danger ? 'danger' : 'primary' }} flex-1" data-modal-confirm="{{ $id }}">
            {{ $confirmLabel ?? __('messages.confirm') }}
        </button>
    </div>
</div>
