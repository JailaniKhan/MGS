@props([
    'id' => '',
    'title' => '',
    'subtitle' => '',
    'amount' => 0,
    'currency' => '',
    'status' => null,
    'statusColor' => 'warning',
    'route' => '#',
    'avatar' => null,
])

<a href="{{ $route }}" class="list-row {{ request()->url() === $route ? 'bg-ink-50 dark:bg-white/[0.02]' : '' }}">
    <div class="flex items-center gap-3 min-w-0 flex-1">
        @if ($avatar)
            <div class="w-9 h-9 rounded-xl bg-brand text-white flex items-center justify-center flex-shrink-0 shadow-sm text-[10px] font-bold">
                {{ $avatar }}
            </div>
        @else
            <div class="w-9 h-9 rounded-xl bg-ink-100 dark:bg-white/[0.05] flex items-center justify-center flex-shrink-0">
                <x-icon name="clipboard-document-list" class="w-4 h-4 text-ink-400"/>
            </div>
        @endif

        <div class="min-w-0">
            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 truncate">{{ $title }}</div>
            @if ($subtitle)
                <div class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5">{{ $subtitle }}</div>
            @endif
        </div>
    </div>

    <div class="text-right flex-shrink-0 ml-3">
        @if ($amount !== '')
            <div class="text-sm font-bold text-ink-900 dark:text-ink-100 tabular-nums">
                {{ $amount }}
                @if ($currency)
                    <span class="text-[10px] font-normal text-ink-500">{{ $currency }}</span>
                @endif
            </div>
        @endif
        @if ($statusBadge)
            <span class="inline-flex items-center gap-1 badge mt-1
                @if($statusColor === 'success') badge-success
                @elseif($statusColor === 'danger') badge-danger
                @elseif($statusColor === 'info') badge-info
                @else badge-warning @endif">
                {{ $statusBadge }}
            </span>
        @endif
    </div>
</a>