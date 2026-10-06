<?php

namespace App\Support;

/**
 * Single choke point for reading a file the shop already keeps on the phone.
 *
 * The APK registers one bridge function on the 'Files.*' namespace:
 *
 *   Files.Pick → opens the system document picker (Storage Access Framework),
 *                reads the chosen file and returns its bytes.
 *
 * The WebView can never deliver a file upload: the injected JS turns a native
 * form body into URLSearchParams and PHPWebViewClient forwards a *string* body
 * to Laravel, so $_FILES stays empty on device. Restoring a backup from the
 * phone's own storage therefore goes through the picker instead.
 *
 * In the browser nativephp_call() does not exist, so callers fall back to a
 * plain <input type="file"> upload.
 */
class NativePicker
{
    /** Same ceiling the Kotlin side enforces; keep the two in step. */
    public const MAX_BYTES = 32 * 1024 * 1024;

    /**
     * Is the native picker reachable? Same test NativeDocument uses: the
     * vendor package defines a userland nativephp_call() stub on dev machines,
     * and only the on-device C extension registers it as internal.
     */
    public static function available(): bool
    {
        return NativeDocument::available();
    }

    /**
     * Let the user pick one file and hand back its bytes.
     *
     * @return array{ok: bool, cancelled: bool, filename: ?string, data: ?string, size: int, error: ?string}
     */
    public static function pick(string $mime = 'application/json'): array
    {
        if (! self::available()) {
            return self::failure('bridge_unavailable');
        }

        try {
            $result = nativephp_call('Files.Pick', json_encode(['mime' => $mime], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            report($e);

            return self::failure('Files.Pick failed: '.$e->getMessage());
        }

        $decoded = json_decode((string) $result, true);

        if (! is_array($decoded)) {
            return self::failure('bridge_no_response');
        }

        // The bridge wraps hard failures as {status:"error", code, message} but
        // returns a successful execution unwrapped — including our own in-band
        // {"error": …} maps from Kotlin (user backed out, file too big, …).
        if (($decoded['status'] ?? null) === 'error') {
            return self::failure((string) ($decoded['code'] ?? 'bridge_error'));
        }

        $error = $decoded['error'] ?? null;

        if ($error === 'picker_cancelled') {
            return self::failure('picker_cancelled', cancelled: true);
        }

        if (! empty($error)) {
            return self::failure((string) $error);
        }

        $data = $decoded['data'] ?? null;

        if (($decoded['success'] ?? false) !== true || ! is_string($data) || $data === '') {
            return self::failure('bridge_unexpected_response');
        }

        return [
            'ok' => true,
            'cancelled' => false,
            'filename' => isset($decoded['filename']) ? (string) $decoded['filename'] : null,
            'data' => $data,
            'size' => (int) ($decoded['size'] ?? 0),
            'error' => null,
        ];
    }

    /**
     * @return array{ok: bool, cancelled: bool, filename: ?string, data: ?string, size: int, error: ?string}
     */
    protected static function failure(string $error, bool $cancelled = false): array
    {
        return [
            'ok' => false,
            'cancelled' => $cancelled,
            'filename' => null,
            'data' => null,
            'size' => 0,
            'error' => $error,
        ];
    }
}
