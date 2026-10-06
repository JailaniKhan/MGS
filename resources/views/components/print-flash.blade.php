{{-- Result notice for the print actions on the standalone print pages.

     The PDF routes redirect back here after handing the document to the
     native shell (or the browser download), and this page has no
     app-shell toasts — so the outcome (opened in the viewer / saved /
     failed) surfaces as its own auto-hiding snack-bar. It is never part
     of the printed sheet. --}}

@php
    $flash = session('success') ?: session('error');
    $ok = (bool) session('success');
@endphp

@if ($flash)
    <div class="print-flash {{ $ok ? '' : 'print-flash-error' }}" role="alert">
        @if ($ok)
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m5 13 4 4L19 7"/>
            </svg>
        @else
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 18 18 6M6 6l12 12"/>
            </svg>
        @endif
        <span>{{ $flash }}</span>
        <button type="button" class="print-flash-close" onclick="this.closest('.print-flash').remove()" aria-label="{{ __('messages.cancel') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <script>
        setTimeout(function () {
            var el = document.querySelector('.print-flash');
            if (el) el.remove();
        }, 5000);
    </script>
@endif
