@props([
    'title' => null,
    'description' => null,
    'actionRoute' => null,
    'actionLabel' => null,
])

<div class="empty-state">
    <div class="empty-illustration">
        {{ $slot ?? '' }}
    </div>
    @if ($title)
        <p class="text-sm font-bold text-ink-900 dark:text-white">{{ $title }}</p>
    @endif
    @if ($description)
        <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-1">{{ $description }}</p>
    @endif
    @if ($actionRoute)
        <a href="{{ $actionRoute }}" class="btn-primary mt-3 px-4 py-2 text-xs">{{ $actionLabel }}</a>
    @endif
</div>
