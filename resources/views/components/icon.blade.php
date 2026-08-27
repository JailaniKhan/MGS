@props(['name' => '', 'class' => 'w-5 h-5', 'strokeWidth' => null])

@php
$sw = $strokeWidth ?? '1.5';

// Request-level memoization: each icon file is read + cleaned once per request
// (~500 renders/page otherwise), then reused from a static cache.
static $iconCache = [];

if ($name && ! array_key_exists($name, $iconCache)) {
    $svgPath = resource_path('views/components/icons/' . $name . '.svg');
    if (file_exists($svgPath)) {
        $raw = file_get_contents($svgPath);
        // Remove XML declaration and outer SVG tag, keep inner content
        $raw = preg_replace('/<\?xml[^>]*\?>/', '', $raw);
        $raw = preg_replace('/<svg[^>]*>/', '', $raw);
        $raw = preg_replace('/<\/svg>/', '', $raw);
        $iconCache[$name] = trim($raw);
    } else {
        $iconCache[$name] = null;
    }
}
@endphp

@if ($name && isset($iconCache[$name]) && $iconCache[$name] !== null)
    <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="{{ $sw }}" stroke="currentColor" aria-hidden="true">{!! $iconCache[$name] !!}</svg>
@endif
