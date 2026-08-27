@props([
    'name' => null,       // icon name; omit to render the slot instead
    'href' => null,       // renders an <a> when set, otherwise <button>
    'label' => null,      // aria-label
    'strokeWidth' => 2,
    'tone' => 'ink',      // ink | secondary
])

@php
$tag = $href ? 'a' : 'button';
$base = 'w-9 h-9 rounded-xl bg-white dark:bg-[#18191a] border border-ink-100 dark:border-white/[0.06] flex items-center justify-center transition-all duration-200 active:scale-95 ';
if ($tone === 'secondary') {
    $base .= 'text-secondary-600 dark:text-secondary-400 hover:border-secondary-200 dark:hover:border-secondary-800';
} else {
    $base .= 'text-ink-500 dark:text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 hover:border-ink-200 dark:hover:border-white/[0.12]';
}
$defaults = array_filter([
    'class' => $base,
    'type' => $href ? null : 'button',
    'href' => $href,
    'aria-label' => $label,
], fn ($v) => $v !== null);
@endphp

<{{ $tag }} {{ $attributes->merge($defaults) }}>
    @if ($name)
        <x-icon :name="$name" class="w-4 h-4" :strokeWidth="$strokeWidth"/>
    @else
        {{ $slot }}
    @endif
</{{ $tag }}>
