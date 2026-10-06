package com.nativephp.mobile.bridge.functions

import android.app.Activity
import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import android.util.Base64
import android.util.Log
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.ui.MainActivity
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/**
 * Functions for reading a file the shop already has in the phone's storage.
 * Namespace: "Files.*"
 *
 * The WebView cannot deliver a file upload: the injected JS rewrites a native
 * form body into URLSearchParams and PHPWebViewClient forwards request bodies
 * as strings, so $_FILES never fills on device. Anything that needs the bytes
 * of a real file — the backup restore flow picking backup_*.json out of
 * Downloads/MGS — is therefore read on this side of the wall and handed to PHP
 * as base64.
 *
 *   Files.Pick — open the system document picker (Storage Access Framework),
 *                read the chosen file and return its bytes.
 *
 * execute() runs on the PHP/JNI thread (the same thread Print.* starts
 * intents from), so the picker is launched on the UI thread and this call
 * parks on a latch until the user answers. The latch is what makes the call
 * synchronous, which is the shape NativeDocument already relies on.
 */
object FilePickerFunctions {

    private const val TAG = "FilePickerFunctions"

    /**
     * A shop archive is text and far smaller than this. The cap exists so a
     * mis-tap on a 4K video cannot OOM the WebView: 32MB of JSON is already
     * years of bookkeeping, and base64 inflates it another third.
     */
    private const val MAX_BYTES = 32 * 1024 * 1024

    /** A picker left open this long means the user walked away from the phone. */
    private const val PICK_TIMEOUT_SECONDS = 240L

    /**
     * JSON first, then the types file managers actually report. Android
     * providers are inconsistent about .json — Downloads, Drive and USB sticks
     * routinely hand back application/octet-stream or text/plain.
     */
    private val JSON_MIME_TYPES = arrayOf(
        "application/json",
        "text/json",
        "text/plain",
        "application/octet-stream",
    )

    private fun displayName(context: Context, uri: Uri): String? =
        try {
            context.contentResolver
                .query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)
                ?.use { cursor -> if (cursor.moveToFirst()) cursor.getString(0) else null }
        } catch (e: Exception) {
            Log.w(TAG, "Could not read display name: ${e.message}")
            null
        }

    /**
     * The size the provider advertises. Downloads and Drive always have it, but
     * it is only a hint — the bytes are measured again after reading.
     */
    private fun declaredSize(context: Context, uri: Uri): Long? =
        try {
            context.contentResolver
                .query(uri, arrayOf(OpenableColumns.SIZE), null, null, null)
                ?.use { cursor ->
                    if (cursor.moveToFirst() && ! cursor.isNull(0)) cursor.getLong(0) else null
                }
        } catch (e: Exception) {
            Log.w(TAG, "Could not read declared size: ${e.message}")
            null
        }

    /**
     * Read the bytes of one file the shop picked from the phone's storage.
     * Parameters:
     *   - mime: string - single expected type, defaults to "application/json"
     *
     * Returns {success, filename, size, data(base64)} or {error, …}:
     *   no_activity      — the bridge was called without a host activity
     *   picker_cancelled — the user backed out of the picker
     *   picker_timeout   — no answer within PICK_TIMEOUT_SECONDS
     *   file_too_large   — over MAX_BYTES (carries `size`)
     *   unreadable_file  — the provider refused the read
     *
     * Usage Example (PHP):
     *   nativephp_call('Files.Pick', json_encode(['mime' => 'application/json']));
     */
    class Pick(private val activity: Activity, private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val host = activity as? MainActivity
                ?: return mapOf("error" to "no_activity")

            val requested = (parameters["mime"] as? String)?.takeIf { it.isNotBlank() }
            val types = when (requested) {
                null, "application/json" -> JSON_MIME_TYPES
                else -> arrayOf(requested)
            }

            val latch = CountDownLatch(1)
            // Written on the UI thread, read here. CountDownLatch supplies the
            // happens-before edge, so no extra synchronisation is needed.
            var picked: Uri? = null

            host.runOnUiThread {
                val opened = host.startDocumentPick(types) { uri ->
                    picked = uri
                    latch.countDown()
                }

                if (! opened) {
                    latch.countDown()
                }
            }

            val answered = try {
                latch.await(PICK_TIMEOUT_SECONDS, TimeUnit.SECONDS)
            } catch (e: InterruptedException) {
                Thread.currentThread().interrupt()
                false
            }

            if (! answered) {
                Log.w(TAG, "⏳ No picker answer within ${PICK_TIMEOUT_SECONDS}s")
                return mapOf("error" to "picker_timeout")
            }

            val uri = picked ?: return mapOf("error" to "picker_cancelled")

            val declared = declaredSize(context, uri)

            if (declared != null && declared > MAX_BYTES) {
                Log.w(TAG, "⚠️ Picked file is $declared bytes — over the $MAX_BYTES cap")

                return mapOf("error" to "file_too_large", "size" to declared)
            }

            val bytes = try {
                context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
            } catch (e: Exception) {
                Log.e(TAG, "❌ Failed to read the picked file: ${e.message}", e)
                null
            }

            if (bytes == null) {
                return mapOf("error" to "unreadable_file")
            }

            // The declared size is only a hint: measure what actually arrived.
            if (bytes.size > MAX_BYTES) {
                return mapOf("error" to "file_too_large", "size" to bytes.size)
            }

            val name = displayName(context, uri) ?: "picked-file"

            Log.d(TAG, "📥 Picked $name (${bytes.size} bytes)")

            return mapOf(
                "success" to true,
                "filename" to name,
                "size" to bytes.size,
                "data" to Base64.encodeToString(bytes, Base64.NO_WRAP),
            )
        }
    }
}
