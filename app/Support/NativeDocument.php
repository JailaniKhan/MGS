<?php

namespace App\Support;

/**
 * Single choke point for handing binary documents (invoice PDFs, purchase
 * bills, cashbook statements, backup archives) to the native shell. The APK
 * registers three bridge functions on the 'Print.*' namespace:
 *
 *   Print.File   → written to a FileProvider-mapped cache file and opened with
 *                  the system viewer / share sheet (transient look)
 *   Print.Save   → written into the phone's Downloads/MGS folder, so the shop
 *                  keeps a real copy (Android 10+ needs no permission; older
 *                  devices are asked for storage access on the first attempt)
 *   Print.Share  → handed to the share sheet with WhatsApp pre-selected
 *
 * In the browser nativephp_call() does not exist, so callers fall back to the
 * HTML print page / a plain PDF download.
 */
class NativeDocument
{
    /** The Downloads sub-folder every saved document lands in. */
    public const FOLDER = 'MGS';

    protected static ?bool $available = null;

    /**
     * Are we running inside the APK with a real native bridge?
     *
     * function_exists() alone is not enough: the vendor package loads a
     * userland Jump-hybrid fallback (jump_bridge_functions.php) on the dev
     * machine that also defines nativephp_call(). Only the on-device C
     * extension registers it as an internal function, so that is what we
     * test for.
     */
    public static function available(): bool
    {
        if (self::$available === null) {
            self::$available = function_exists('nativephp_call')
                && (new \ReflectionFunction('nativephp_call'))->isInternal();
        }

        return self::$available;
    }

    /**
     * Open a document once with the system viewer.
     *
     * @return bool true when the bytes reached the native shell.
     */
    public static function open(string $binary, string $filename, string $mime = 'application/pdf', string $title = ''): bool
    {
        return self::call('Print.File', [
            'data' => base64_encode($binary),
            'filename' => $filename,
            'mime' => $mime,
            'title' => $title ?: $filename,
        ])['ok'];
    }

    /**
     * Save a real copy into the phone's Downloads/MGS folder.
     *
     * @return array{ok: bool, error: ?string, path: ?string, needs_permission: bool}
     */
    public static function save(string $binary, string $filename, string $mime = 'application/pdf', string $folder = self::FOLDER): array
    {
        return self::call('Print.Save', [
            'data' => base64_encode($binary),
            'filename' => $filename,
            'mime' => $mime,
            'folder' => $folder,
        ], 'bridge_unavailable');
    }

    /**
     * Hand the document to the share sheet, WhatsApp first.
     *
     * @return array{ok: bool, error: ?string, path: ?string, target: ?string, needs_permission: bool}
     */
    public static function share(
        string $binary,
        string $filename,
        string $title = '',
        string $mime = 'application/pdf',
        string $text = '',
        string $package = 'com.whatsapp',
    ): array {
        return self::call('Print.Share', [
            'data' => base64_encode($binary),
            'filename' => $filename,
            'mime' => $mime,
            'title' => $title ?: $filename,
            'text' => $text,
            'package' => $package,
        ], 'bridge_unavailable');
    }

    /**
     * Run one bridge call and normalise its answer.
     *
     * The bridge wraps hard failures as {status:"error", code, message} but
     * returns a successful execution unwrapped — including our own in-band
     * {"error": …} maps from Kotlin (no viewer app installed, storage
     * permission missing, …). Both must read as a failure here, otherwise the
     * UI reports success while nothing happened.
     *
     * @return array{ok: bool, error: ?string, path: ?string, target: ?string, needs_permission: bool}
     */
    protected static function call(string $function, array $payload, string $unavailable = 'bridge_unavailable'): array
    {
        if (! self::available()) {
            return self::failure($unavailable);
        }

        try {
            $result = nativephp_call($function, json_encode($payload, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return self::failure($function.' failed: '.$e->getMessage());
        }

        $decoded = json_decode((string) $result, true);

        if (! is_array($decoded)) {
            return self::failure('bridge_no_response');
        }

        if (($decoded['status'] ?? null) === 'error') {
            return self::failure((string) ($decoded['code'] ?? 'bridge_error'), $decoded);
        }

        if (! empty($decoded['error'])) {
            return self::failure((string) $decoded['error'], $decoded);
        }

        if (($decoded['success'] ?? false) !== true && ! isset($decoded['path'])) {
            return self::failure('bridge_unexpected_response', $decoded);
        }

        return [
            'ok' => true,
            'error' => null,
            'path' => isset($decoded['path']) ? (string) $decoded['path'] : null,
            'target' => isset($decoded['target']) ? (string) $decoded['target'] : null,
            'needs_permission' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{ok: bool, error: ?string, path: ?string, target: ?string, needs_permission: bool}
     */
    protected static function failure(string $error, array $decoded = []): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'path' => null,
            'target' => null,
            'needs_permission' => (bool) ($decoded['needs_permission'] ?? false),
        ];
    }
}
