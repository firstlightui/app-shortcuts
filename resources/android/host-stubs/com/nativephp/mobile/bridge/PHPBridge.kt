// Stand-ins for NativePHP's PHPBridge and LaravelEnvironment, used only by the hostCheck compile.
// Signatures copied from nativephp/mobile 4.7 resources/androidstudio/.../bridge/{PHPBridge,LaravelEnvironment}.kt.
package com.nativephp.mobile.bridge

import android.content.Context

class PHPBridge(private val context: Context) {
    external fun nativeEphemeralBoot(bootstrapPath: String): Int
    external fun nativeEphemeralArtisan(command: String): String
    external fun nativeEphemeralShutdown()

    fun bootPersistentRuntime(): Boolean = false

    fun getLaravelPath(): String = ""
}

class LaravelEnvironment(private val context: Context) {
    fun initializeForBackground() {}

    companion object {
        fun getRuntimeMode(context: Context): String = "persistent"
    }
}
