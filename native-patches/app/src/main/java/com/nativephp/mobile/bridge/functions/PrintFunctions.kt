package com.nativephp.mobile.bridge.functions

import android.Manifest
import android.app.Activity
import android.content.ClipData
import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.provider.MediaStore
import android.util.Base64
import android.util.Log
import android.widget.Toast
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import com.nativephp.mobile.bridge.BridgeFunction
import java.io.File
import java.io.FileOutputStream

/**
 * Functions for getting generated documents (PDF invoices, purchase bills,
 * cashbook statements, backup archives) out of the app and onto the phone.
 * Namespace: "Print.*"
 *
 * The Android WebView can neither render PDFs, download files nor run
 * window.print(), so PHP hands the raw bytes over the bridge and this side of
 * the wall does what a browser would:
 *
 *   Print.File   — open once with the system viewer / share sheet (transient)
 *   Print.Save   — keep a real copy in the phone's Downloads/MGS folder
 *   Print.Share  — ACTION_SEND straight into WhatsApp (or the chooser)
 */
object PrintFunctions {

    private const val TAG = "PrintFunctions"

    /** Documents are filed under Downloads/<FOLDER>/ so the shop can find them. */
    private const val DEFAULT_FOLDER = "MGS"

    /** WhatsApp first, its Business twin second. */
    private val WHATSAPP_PACKAGES = listOf("com.whatsapp", "com.whatsapp.w4b")

    /** Request code for the pre-Android-10 storage permission round trip. */
    private const val STORAGE_PERMISSION_REQUEST = 4711

    // ---------------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------------

    private fun decode(parameters: Map<String, Any>): ByteArray? =
        (parameters["data"] as? String)?.let {
            try {
                Base64.decode(it, Base64.DEFAULT)
            } catch (e: IllegalArgumentException) {
                Log.e(TAG, "Invalid base64 payload: ${e.message}")
                null
            }
        }

    /** Caller-supplied names must never escape the target directory. */
    private fun safeName(parameters: Map<String, Any>, extension: String = "pdf"): String =
        (parameters["filename"] as? String)
            ?.replace(Regex("[/\\\\]"), "_")
            ?.takeIf { it.isNotBlank() && !it.startsWith(".") }
            ?: "document-${System.currentTimeMillis()}.$extension"

    private fun mime(parameters: Map<String, Any>): String =
        (parameters["mime"] as? String)?.takeIf { it.isNotBlank() } ?: "application/pdf"

    private fun folder(parameters: Map<String, Any>): String =
        (parameters["folder"] as? String)
            ?.replace(Regex("[/\\\\]"), "")
            ?.takeIf { it.isNotBlank() }
            ?: DEFAULT_FOLDER

    /** A FileProvider-readable copy in the app cache — the shareable twin. */
    private fun cacheCopy(context: Context, name: String, bytes: ByteArray): File {
        val dir = File(context.cacheDir, "print").apply { mkdirs() }
        return File(dir, name).apply { writeBytes(bytes) }
    }

    private fun uriFor(context: Context, file: File): Uri =
        FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)

    private fun installed(context: Context, packageName: String): Boolean =
        try {
            context.packageManager.getPackageInfo(packageName, 0)
            true
        } catch (e: PackageManager.NameNotFoundException) {
            false
        }

    /** WhatsApp (or WhatsApp Business) when it is on this phone. */
    private fun whatsAppPackage(context: Context): String? =
        WHATSAPP_PACKAGES.firstOrNull { installed(context, it) }

    private fun toast(activity: Activity?, message: String) {
        if (activity == null) {
            return
        }

        activity.runOnUiThread {
            Toast.makeText(activity, message, Toast.LENGTH_LONG).show()
        }
    }

    /**
     * ACTION_SEND intent for one cached document. ClipData keeps the URI read
     * grant alive on newer Androids; NEW_TASK is required off the JNI thread.
     */
    private fun shareIntent(
        context: Context,
        uri: Uri,
        name: String,
        type: String,
        title: String,
        text: String,
    ): Intent = Intent(Intent.ACTION_SEND).apply {
        setType(type)
        putExtra(Intent.EXTRA_STREAM, uri)
        putExtra(Intent.EXTRA_SUBJECT, title)
        if (text.isNotBlank()) {
            putExtra(Intent.EXTRA_TEXT, text)
        }
        clipData = ClipData.newUri(context.contentResolver, name, uri)
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
    }

    /**
     * Open a binary document with the system viewer / share sheet.
     * Parameters:
     *   - data: string (required) - base64-encoded file bytes
     *   - filename: string - target file name inside the print cache dir
     *   - mime: string - defaults to "application/pdf"
     *   - title: string - chooser title
     *
     * Usage Example (PHP):
     *   nativephp_call('Print.File', json_encode([
     *     'data' => base64_encode($pdfBinary),
     *     'filename' => 'INV-12.pdf',
     *   ]));
     */
    class Open(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val bytes = decode(parameters)
                ?: return mapOf("error" to "Missing base64 'data' parameter")

            val name = safeName(parameters)
            val type = mime(parameters)
            val title = (parameters["title"] as? String)?.takeIf { it.isNotBlank() }
                ?: "Open document"

            return try {
                val file = cacheCopy(context, name, bytes)
                val uri = uriFor(context, file)

                val intent = Intent(Intent.ACTION_VIEW).apply {
                    setDataAndType(uri, type)
                    // NEW_TASK is required: this runs on the PHP/JNI thread,
                    // not from an activity context.
                    addFlags(
                        Intent.FLAG_GRANT_READ_URI_PERMISSION
                            or Intent.FLAG_ACTIVITY_NEW_TASK
                    )
                }

                try {
                    context.startActivity(Intent.createChooser(intent, title))
                } catch (e: Exception) {
                    // No PDF viewer installed — a share sheet still gets the
                    // document into Files, Drive, WhatsApp, …
                    context.startActivity(
                        Intent.createChooser(shareIntent(context, uri, name, type, title, ""), title)
                    )
                }

                Log.d(TAG, "📄 Opened ${file.name} ($type, ${bytes.size} bytes)")
                mapOf("success" to true, "path" to file.absolutePath)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Failed to open document: ${e.message}", e)
                mapOf("error" to (e.message ?: "Failed to open document"))
            }
        }
    }

    /**
     * Save a document into the phone's Downloads folder (Downloads/MGS/).
     *
     * Android 10+ writes through MediaStore — the file shows up in the Files
     * app and no permission is needed. Android 9 and older write the classic
     * way and ask for WRITE_EXTERNAL_STORAGE first; when the user has not
     * granted it yet the call answers needs_permission so PHP can tell them to
     * tap Save again.
     * Parameters:
     *   - data: string (required) - base64-encoded file bytes
     *   - filename: string - name shown in Downloads/MGS
     *   - mime: string - defaults to "application/pdf"
     *   - folder: string - sub-folder inside Downloads, defaults to "MGS"
     *
     * Usage Example (PHP):
     *   nativephp_call('Print.Save', json_encode([
     *     'data' => base64_encode($pdfBinary),
     *     'filename' => 'INV-12.pdf',
     *   ]));
     */
    class Save(private val activity: Activity?, private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val bytes = decode(parameters)
                ?: return mapOf("error" to "Missing base64 'data' parameter")

            val name = safeName(parameters)
            val type = mime(parameters)
            val target = folder(parameters)

            return try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    saveViaMediaStore(name, type, bytes, target)
                } else {
                    saveToPublicDownloads(name, type, bytes, target)
                }
            } catch (e: Exception) {
                Log.e(TAG, "❌ Failed to save document: ${e.message}", e)
                mapOf("error" to (e.message ?: "Failed to save document"))
            }
        }

        /** Android 10+: MediaStore owns Downloads, so no permission is needed. */
        private fun saveViaMediaStore(
            name: String,
            type: String,
            bytes: ByteArray,
            target: String,
        ): Map<String, Any> {
            val relative = Environment.DIRECTORY_DOWNLOADS + "/" + target

            val values = ContentValues().apply {
                put(MediaStore.MediaColumns.DISPLAY_NAME, name)
                put(MediaStore.MediaColumns.MIME_TYPE, type)
                put(MediaStore.MediaColumns.RELATIVE_PATH, relative)
                put(MediaStore.MediaColumns.IS_PENDING, 1)
            }

            val resolver = context.contentResolver
            val uri = resolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values)
                ?: throw IllegalStateException("Downloads provider rejected the file")

            resolver.openOutputStream(uri)?.use { it.write(bytes) }
                ?: throw IllegalStateException("Could not open the target file")

            values.clear()
            values.put(MediaStore.MediaColumns.IS_PENDING, 0)
            resolver.update(uri, values, null, null)

            toast(activity, "📄 $name → Downloads/$target")

            return mapOf(
                "success" to true,
                "path" to "$relative/$name",
                "uri" to uri.toString(),
            )
        }

        /** Android 9 and older: public Downloads needs the storage permission. */
        private fun saveToPublicDownloads(
            name: String,
            type: String,
            bytes: ByteArray,
            target: String,
        ): Map<String, Any> {
            val granted = ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.WRITE_EXTERNAL_STORAGE
            ) == PackageManager.PERMISSION_GRANTED

            if (! granted) {
                // Ask once; the user taps Save again after allowing storage.
                activity?.let {
                    it.runOnUiThread {
                        ActivityCompat.requestPermissions(
                            it,
                            arrayOf(Manifest.permission.WRITE_EXTERNAL_STORAGE),
                            STORAGE_PERMISSION_REQUEST
                        )
                    }
                }

                Log.w(TAG, "⚠️ Storage permission missing — asked for it, user must retry")
                return mapOf(
                    "error" to "storage_permission_required",
                    "needs_permission" to true,
                )
            }

            val dir = File(
                Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS),
                target
            ).apply { mkdirs() }

            return try {
                val file = File(dir, name)
                FileOutputStream(file).use { it.write(bytes) }
                toast(activity, "📄 $name → Downloads/$target")

                mapOf("success" to true, "path" to file.absolutePath)
            } catch (e: Exception) {
                // Some OEM builds keep the public Downloads folder read-only
                // even with the permission; the app's own external dir always
                // works and still shows up over USB / in the Files app.
                val fallback = File(context.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), name)
                FileOutputStream(fallback).use { it.write(bytes) }
                toast(activity, "📄 $name saved in app storage")

                mapOf("success" to true, "path" to fallback.absolutePath, "private" to true)
            }
        }
    }

    /**
     * Hand a document to the share sheet, targeting WhatsApp by default.
     * Parameters:
     *   - data: string (required) - base64-encoded file bytes
     *   - filename: string - name WhatsApp shows on the attachment
     *   - mime: string - defaults to "application/pdf"
     *   - title: string - chooser / subject title
     *   - text: string - optional caption sent alongside the file
     *   - package: string - app to open, defaults to WhatsApp
     *
     * Usage Example (PHP):
     *   nativephp_call('Print.Share', json_encode([
     *     'data' => base64_encode($pdfBinary),
     *     'filename' => 'INV-12.pdf',
     *     'text' => 'Invoice INV-12',
     *   ]));
     */
    class Share(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val bytes = decode(parameters)
                ?: return mapOf("error" to "Missing base64 'data' parameter")

            val name = safeName(parameters)
            val type = mime(parameters)
            val title = (parameters["title"] as? String)?.takeIf { it.isNotBlank() }
                ?: "Share document"
            val text = (parameters["text"] as? String).orEmpty()
            val requested = (parameters["package"] as? String)?.takeIf { it.isNotBlank() }

            return try {
                val file = cacheCopy(context, name, bytes)
                val uri = uriFor(context, file)
                val send = shareIntent(context, uri, name, type, title, text)

                // Prefer the requested app (WhatsApp), else whichever WhatsApp
                // build is installed, else the full chooser.
                val target = requested?.takeIf { installed(context, it) } ?: whatsAppPackage(context)

                val opened = if (target != null) {
                    try {
                        context.startActivity(Intent(send).setPackage(target))
                        target
                    } catch (e: Exception) {
                        // Target installed but the intent cannot be delivered —
                        // fall back to the chooser instead of failing.
                        context.startActivity(Intent.createChooser(send, title))
                        "chooser"
                    }
                } else {
                    context.startActivity(Intent.createChooser(send, title))
                    "chooser"
                }

                Log.d(TAG, "📤 Shared ${file.name} via $opened")
                mapOf("success" to true, "path" to file.absolutePath, "target" to opened)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Failed to share document: ${e.message}", e)
                mapOf("error" to (e.message ?: "Failed to share document"))
            }
        }
    }
}
