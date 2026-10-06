{{-- Action bar for the standalone print pages.

     Desktop browser: the classic print dialog. On the phone (APK) the
     Android WebView can neither render PDFs nor run window.print(), so
     the same bar goes over the native bridge — Print opens the PDF in
     the system viewer (whose menu owns the actual printing), Save keeps
     a copy in Downloads, WhatsApp hands it to the share sheet. The bar
     only exists so the preview is never a dead end; x-print-flash shows
     the outcome when the redirect lands back here. --}}
@props(['open', 'save', 'share', 'whatsapp'])

@php($isDevice = \App\Support\NativeDocument::available())

<div class="actions">
    <div class="actions-inner">
        @if ($isDevice)
            <form action="{{ $open }}" method="POST">
                @csrf
                <button type="submit" class="action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 9V3h12v6"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <path d="M6 14h12v7H6z"/>
                    </svg>
                    <span>{{ __('messages.print') }}</span>
                </button>
            </form>
            <form action="{{ $save }}" method="POST">
                @csrf
                <button type="submit" class="action-btn action-btn-quiet">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5"/>
                        <path d="M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    <span>{{ __('messages.save_pdf') }}</span>
                </button>
            </form>
            <form action="{{ $whatsapp }}" method="POST">
                @csrf
                <button type="submit" class="action-btn action-btn-quiet">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                    </svg>
                    <span>{{ __('messages.send_pdf_whatsapp') }}</span>
                </button>
            </form>
        @else
            <button type="button" class="action-btn" onclick="window.print()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 9V3h12v6"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <path d="M6 14h12v7H6z"/>
                </svg>
                <span>{{ __('messages.print') }}</span>
            </button>
        @endif
    </div>
</div>
