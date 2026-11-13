package dev.firstlightui.plugins.app_shortcuts

import android.content.Context
import android.content.Intent
import android.net.Uri

/** The links shortcuts carry, matching src/Build/AndroidShortcuts.php. */
object ShortcutLinks {
    const val SCHEME = "firstlight-shortcut"
    const val ID_PREFIX = "firstlight."
    const val MAIN_ACTIVITY = "com.nativephp.mobile.ui.MainActivity"
    const val TRAMPOLINE = "dev.firstlightui.plugins.app_shortcuts.ShortcutTrampolineActivity"

    /** firstlight-shortcut:///tasks/create: no host, so NativePHP routes the path unchanged. */
    fun uri(path: String): Uri = Uri.parse("$SCHEME://$path")

    /** True for a path the app can route, such as /tasks/create?list=3. */
    fun isAppPath(path: String): Boolean =
        path.startsWith("/") && !path.startsWith("//") && path.none { it.isWhitespace() || it.isISOControl() }

    /** The intent that opens a shortcut's link in MainActivity, or null for a link no shortcut made. */
    fun mainActivityIntent(context: Context, data: Uri?): Intent? {
        if (data == null || data.scheme != SCHEME || !data.host.isNullOrEmpty()) {
            return null
        }

        return Intent(Intent.ACTION_VIEW, data)
            .setClassName(context, MAIN_ACTIVITY)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
    }
}
