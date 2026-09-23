package com.nativephp.mobile.bridge.functions

import android.content.Context
import android.content.Intent
import android.util.Base64
import android.util.Log
import androidx.core.content.FileProvider
import com.nativephp.mobile.bridge.BridgeFunction
import java.io.File

/**
 * Functions for getting generated documents (PDF invoices, backup archives)
 * out of the app. Namespace: "Print.*"
 *
 * The Android WebView cannot render PDFs or handle download responses, so
 * PHP hands the raw bytes over the bridge and this side of the wall does
 * what a browser would: save to a provider-mapped cache file and open the
 * system viewer / share sheet.
 */
object PrintFunctions {

    /**
     * Open a binary document with the system viewer.
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
    class File(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val data = parameters["data"] as? String
                ?: return mapOf("error" to "Missing base64 'data' parameter")

            val safeName = (parameters["filename"] as? String)
                ?.replace(Regex("[/\\\\]"), "_")
                ?.takeIf { it.isNotBlank() && !it.startsWith(".") }
                ?: "document-${System.currentTimeMillis()}.pdf"
            val mime = (parameters["mime"] as? String)?.takeIf { it.isNotBlank() }
                ?: "application/pdf"
            val title = (parameters["title"] as? String)?.takeIf { it.isNotBlank() }
                ?: "Open document"

            return try {
                val bytes = Base64.decode(data, Base64.DEFAULT)
                val dir = File(context.cacheDir, "print").apply { mkdirs() }
                val file = File(dir, safeName)
                file.writeBytes(bytes)

                val uri = FileProvider.getUriForFile(
                    context,
                    "${context.packageName}.fileprovider",
                    file,
                )
                val intent = Intent(Intent.ACTION_VIEW).apply {
                    setDataAndType(uri, mime)
                    // NEW_TASK is required: this runs on the PHP/JNI thread,
                    // not from an activity context.
                    addFlags(
                        Intent.FLAG_GRANT_READ_URI_PERMISSION
                            or Intent.FLAG_ACTIVITY_NEW_TASK
                    )
                }
                context.startActivity(Intent.createChooser(intent, title))

                Log.d("PrintFunctions.File", "📄 Opened ${file.name} ($mime, ${bytes.size} bytes)")
                mapOf("success" to true, "path" to file.absolutePath)
            } catch (e: Exception) {
                Log.e("PrintFunctions.File", "❌ Failed to open document: ${e.message}", e)
                mapOf("error" to (e.message ?: "Failed to open document"))
            }
        }
    }
}
