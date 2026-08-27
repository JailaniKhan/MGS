@props([
    'name',
    'value',
    'label',              // display text
    'checked' => false,
    'tone' => 'brand',    // brand | primary | secondary
    'pill' => false,      // true = sr-only input + big centered bold label (segmented pill)
])

@php
$toneClasses = match ($tone) {
    'secondary' => 'has-[:checked]:border-secondary-500 has-[:checked]:bg-secondary-50 dark:has-[:checked]:bg-secondary-900/20 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-secondary-500/30',
    'primary' => 'has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/30',
    default => 'has-[:checked]:border-brand has-[:checked]:bg-brand/10 dark:has-[:checked]:bg-brand/20 has-[:checked]:text-brand has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/30',
};
$radioColor = match ($tone) {
    'secondary' => 'text-secondary-600',
    default => 'text-primary-600',
};
$base = 'flex items-center justify-center gap-2 px-4 py-3 rounded-xl border cursor-pointer transition-all duration-200 active:scale-[0.98] border-ink-200 dark:border-ink-700 text-sm text-ink-600 dark:text-ink-300 '
    . ($pill ? 'py-3.5 font-bold ' : '')
    . $toneClasses;
@endphp

<label {{ $attributes->merge(['class' => $base]) }}>
    <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked($checked) class="{{ $pill ? 'sr-only' : $radioColor }}">
    <span>{{ $label }}</span>
</label>
