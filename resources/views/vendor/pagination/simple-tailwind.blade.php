@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex gap-2 items-center justify-between">

        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-ink-600 bg-white border border-ink-300 cursor-not-allowed leading-5 rounded-md dark:text-ink-300 dark:bg-ink-700 dark:border-ink-600">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-4 py-2 text-sm font-medium text-ink-800 bg-white border border-ink-300 leading-5 rounded-md hover:text-ink-700 focus:outline-none focus:ring ring-ink-300 focus:border-secondary-300 active:bg-ink-100 active:text-ink-800 transition ease-in-out duration-150 dark:bg-ink-800 dark:border-ink-600 dark:text-ink-200 dark:focus:border-secondary-700 dark:active:bg-ink-700 dark:active:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-900 dark:hover:text-ink-200">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-4 py-2 text-sm font-medium text-ink-800 bg-white border border-ink-300 leading-5 rounded-md hover:text-ink-700 focus:outline-none focus:ring ring-ink-300 focus:border-secondary-300 active:bg-ink-100 active:text-ink-800 transition ease-in-out duration-150 dark:bg-ink-800 dark:border-ink-600 dark:text-ink-200 dark:focus:border-secondary-700 dark:active:bg-ink-700 dark:active:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-900 dark:hover:text-ink-200">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-ink-600 bg-white border border-ink-300 cursor-not-allowed leading-5 rounded-md dark:text-ink-300 dark:bg-ink-700 dark:border-ink-600">
                {!! __('pagination.next') !!}
            </span>
        @endif

    </nav>
@endif
