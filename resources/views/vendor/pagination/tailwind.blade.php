@if ($paginator->hasPages())
    @php
        $pill = 'min-w-[2.25rem] h-9 px-3 inline-flex items-center justify-center gap-1 rounded-full text-xs font-semibold transition-all duration-200 select-none';
        $idle = $pill.' text-ink-600 dark:text-ink-300 bg-white dark:bg-white/[0.06] border border-ink-200 dark:border-white/[0.08] hover:bg-ink-100 dark:hover:bg-white/10 active:scale-95';
        $off = $pill.' text-ink-400 dark:text-ink-500 bg-transparent border border-ink-100 dark:border-white/[0.06] opacity-60 cursor-not-allowed';
        $active = $pill.' bg-brand text-white shadow-btn cursor-default';
        $showing = __('messages.pagination_showing', [
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
            'total' => $paginator->total(),
        ]);
    @endphp

    <nav role="navigation" aria-label="{{ __('messages.pagination_label') }}" class="mt-1">

        {{-- Mobile: prev / next only --}}
        <div class="flex items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="{{ $off }}">{{ __('messages.pagination_prev') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $idle }}">{{ __('messages.pagination_prev') }}</a>
            @endif

            <span class="text-[11px] font-semibold text-ink-500 dark:text-ink-400 tabular-nums">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $idle }}">{{ __('messages.pagination_next') }}</a>
            @else
                <span class="{{ $off }}">{{ __('messages.pagination_next') }}</span>
            @endif
        </div>

        {{-- Desktop: summary + full pager --}}
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between sm:gap-3">
            <p class="text-[11px] font-medium text-ink-500 dark:text-ink-400 tabular-nums">{{ $showing }}</p>

            <span class="inline-flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $off }}" aria-disabled="true" aria-label="{{ __('messages.pagination_prev') }}">
                        <x-icon name="chevron-left" class="w-4 h-4 rtl:-scale-x-100"/>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('messages.pagination_prev') }}" class="{{ $idle }}">
                        <x-icon name="chevron-left" class="w-4 h-4 rtl:-scale-x-100"/>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true" class="{{ $pill }} !border-transparent !bg-transparent text-ink-400 cursor-default">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $active }}">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $idle }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('messages.pagination_next') }}" class="{{ $idle }}">
                        <x-icon name="chevron-right" class="w-4 h-4 rtl:-scale-x-100"/>
                    </a>
                @else
                    <span class="{{ $off }}" aria-disabled="true" aria-label="{{ __('messages.pagination_next') }}">
                        <x-icon name="chevron-right" class="w-4 h-4 rtl:-scale-x-100"/>
                    </span>
                @endif
            </span>
        </div>
    </nav>
@endif
