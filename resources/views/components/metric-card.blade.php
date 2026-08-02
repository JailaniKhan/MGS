@props([
    'label' => '',
    'value' => 0,
    'suffix' => null,
    'color' => 'primary',
    'trend' => null,
    'trendLabel' => null,
])

<div {{ $attributes->merge(['class' => 'metric-tile relative']) }}>
    <span class="metric-label">{{ $label }}</span>
    <span class="metric-value {{ $trend ? 'mt-0.5 font-bold text-xl' : 'text-lg font-bold' }} text-ink-900 dark:text-white tabular-nums">
        {{ is_numeric($value) ? number_format($value) : $value }}
        @if ($suffix)
            <span class="text-[11px] font-medium text-ink-400">{{ $suffix }}</span>
        @endif
    </span>
    @if ($trend !== null)
        <div class="flex items-center gap-1 mt-1">
            @if ($trend > 0)
                <x-icon name="arrow-trending-up" class="w-3 h-3 text-primary-500" strokeWidth="2"/>
                <span class="text-[10px] font-bold text-primary-500">{{ $trend }}%</span>
            @elseif ($trend < 0)
                <x-icon name="arrow-trending-down" class="w-3 h-3 text-danger-500" strokeWidth="2"/>
                <span class="text-[10px] font-bold text-danger-500">{{ abs($trend) }}%</span>
            @endif
            @if ($trendLabel)
                <span class="text-[9px] text-ink-400">{{ $trendLabel }}</span>
            @endif
        </div>
    @endif
</div>