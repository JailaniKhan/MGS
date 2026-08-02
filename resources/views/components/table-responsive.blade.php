@props([
    'headers' => [],
    'emptyText' => __('messages.no_results'),
])

<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    @if ($headers)
        <div class="hidden md:flex items-center px-4 py-2.5 bg-ink-50 dark:bg-white/[0.03] text-[10px] font-bold text-ink-500 dark:text-ink-400 uppercase tracking-wide border-b border-ink-100 dark:border-white/[0.06]">
            @foreach ($headers as $header)
                <div class="{{ $header_class ?? 'flex-1 min-w-0' }}">{{ $header }}</div>
            @endforeach
        </div>
    @endif
    <div class="divide-y divide-ink-100 dark:divide-ink-700/30">
        {{ $slot }}
    </div>
</div>