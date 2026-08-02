@props([
    'editRoute' => null,
    'deleteRoute' => null,
    'confirmMessage' => null,
])

<div class="swipe-row group/list border-b border-ink-100 dark:border-white/[0.06]">
    <div class="swipe-content flex items-center gap-3 px-4 py-3.5 cursor-pointer
                bg-white dark:bg-[#16181c] hover:bg-ink-50 dark:hover:bg-white/[0.03]
                active:bg-ink-100 dark:active:bg-white/[0.05]">
        {{ $slot }}
    </div>
    <div class="swipe-actions">
        @if ($editRoute)
            <a href="{{ $editRoute }}" class="act-edit flex items-center justify-center w-12">
                <x-icon name="pencil-square" class="w-5 h-5"/>
            </a>
        @endif
        @if ($deleteRoute)
            <form action="{{ $deleteRoute }}" method="POST"
                  onsubmit="return confirm('{{ $confirmMessage ?? __('messages.confirm_delete') }}')"
                  class="flex">
                @csrf
                @method('DELETE')
                <button type="submit" class="act-danger flex items-center justify-center h-full w-12">
                    <x-icon name="trash" class="w-5 h-5"/>
                </button>
            </form>
        @endif
    </div>
</div>