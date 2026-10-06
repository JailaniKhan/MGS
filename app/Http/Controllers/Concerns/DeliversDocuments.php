<?php

namespace App\Http\Controllers\Concerns;

use App\Support\NativeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The three device-side things a shop does with a rendered PDF: look at it,
 * keep it on the phone, or hand it to WhatsApp. Order invoices, purchase
 * bills and cashbook statements all share this path so the flash messages,
 * the storage-permission hint and the browser fallback stay identical.
 *
 * The Android WebView can do none of it natively, so the bytes travel over
 * the native bridge (Print.*) instead; on a desktop browser every action
 * degrades to a plain PDF download.
 */
trait DeliversDocuments
{
    // These PDF endpoints are POST-only, so back() cannot be trusted: when the
    // browser does not send a usable Referer it falls back to the request URL
    // itself (e.g. orders/3/pdf/open) and the Android WebView follows that 3xx
    // as a GET — straight into a 405. Every caller therefore names an explicit,
    // GET-able page to land on via $redirect.

    /** Open the PDF once in the phone's viewer. */
    protected function openDocument(string $binary, string $filename, string $title, ?string $redirect = null): Response|RedirectResponse
    {
        if (! NativeDocument::available()) {
            return $this->documentDownload($binary, $filename);
        }

        $ok = NativeDocument::open($binary, $filename, 'application/pdf', $title);

        return redirect()->to($redirect ?: url()->previous())
            ->with($ok ? 'success' : 'error', $ok
                ? __('messages.document_opened')
                : __('messages.document_open_failed'));
    }

    /** Save a real copy into Downloads/MGS on the phone. */
    protected function saveDocument(string $binary, string $filename, string $title, ?string $redirect = null): Response|RedirectResponse
    {
        if (! NativeDocument::available()) {
            return $this->documentDownload($binary, $filename);
        }

        $result = NativeDocument::save($binary, $filename, 'application/pdf');

        if ($result['ok']) {
            return redirect()->to($redirect ?: url()->previous())
                ->with('success', __('messages.document_saved', [
                    'path' => $result['path'] ?: $filename,
                ]));
        }

        return redirect()->to($redirect ?: url()->previous())->with(
            'error',
            $result['needs_permission']
                ? __('messages.storage_permission_needed')
                : __('messages.document_save_failed')
        );
    }

    /** Hand the PDF to the share sheet with WhatsApp pre-selected. */
    protected function shareDocument(string $binary, string $filename, string $title, string $text = '', ?string $redirect = null): Response|RedirectResponse
    {
        if (! NativeDocument::available()) {
            return $this->documentDownload($binary, $filename);
        }

        $result = NativeDocument::share($binary, $filename, $title, 'application/pdf', $text);

        return redirect()->to($redirect ?: url()->previous())
            ->with($result['ok'] ? 'success' : 'error', $result['ok']
                ? __('messages.document_shared')
                : __('messages.document_share_failed'));
    }

    /** Browser fallback: the same bytes as a normal file download. */
    protected function documentDownload(string $binary, string $filename): Response
    {
        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
