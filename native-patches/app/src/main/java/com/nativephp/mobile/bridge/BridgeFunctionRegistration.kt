package com.nativephp.mobile.bridge

import android.content.Context
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.functions.EdgeFunctions
import com.nativephp.mobile.bridge.functions.FilePickerFunctions
import com.nativephp.mobile.bridge.functions.PrintFunctions
import com.nativephp.mobile.bridge.plugins.registerPluginBridgeFunctions

/**
 * Register all bridge functions with the registry
 * Call this once during app initialization
 */
fun registerBridgeFunctions(activity: FragmentActivity, context: Context) {
    val registry = BridgeFunctionRegistry.shared

    registry.register("Edge.Set", EdgeFunctions.Set())
    // Print.* — the WebView has no print pipeline, so PHP ships document
    // bytes over the bridge: File = view once, Save = Downloads/MGS,
    // Share = ACTION_SEND into WhatsApp.
    registry.register("Print.File", PrintFunctions.Open(context))
    registry.register("Print.Save", PrintFunctions.Save(activity, context))
    registry.register("Print.Share", PrintFunctions.Share(context))
    // Files.* — the WebView cannot upload a file (request bodies reach Laravel
    // as strings, so $_FILES stays empty), so PHP asks the native side to open
    // the system document picker and hand the chosen bytes back as base64.
    registry.register("Files.Pick", FilePickerFunctions.Pick(activity, context))

    // Register plugin bridge functions
    registerPluginBridgeFunctions(activity, context)
}