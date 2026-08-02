@props(['name' => '', 'class' => 'w-5 h-5', 'strokeWidth' => null])

@php
$sw = $strokeWidth ?? '1.5';
@endphp

@if ($name)
    @php
        $svgPath = resource_path('views/components/icons/' . $name . '.svg');
    @endphp
    @if(file_exists($svgPath))
        @php
            $raw = file_get_contents($svgPath);
            // Remove XML declaration and outer SVG tag, keep inner content
            $raw = preg_replace('/<\?xml[^>]*\?>/', '', $raw);
            $raw = preg_replace('/<svg[^>]*>/', '', $raw);
            $raw = preg_replace('/<\/svg>/', '', $raw);
            $raw = trim($raw);
        @endphp
        <svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="{{ $sw }}" stroke="currentColor" aria-hidden="true">{!! $raw !!}</svg>
    @endif
@endif