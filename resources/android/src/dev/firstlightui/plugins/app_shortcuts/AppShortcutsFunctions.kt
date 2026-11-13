package dev.firstlightui.plugins.app_shortcuts

import android.content.Context
import android.content.Intent
import android.content.pm.ShortcutInfo
import android.content.pm.ShortcutManager
import android.graphics.drawable.Icon
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse

/**
 * Bridge functions called from PHP through nativephp_call(). They run on the PHP thread, which
 * ShortcutManager allows. Only shortcuts with this plugin's id prefix are touched, so another
 * plugin's dynamic shortcuts are never evicted or cleared.
 */
object AppShortcutsFunctions {
    /** AppShortcuts.Push {"id", "title", "shortTitle", "path", "icon"}; Android ignores the icon. */
    class Push(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
            val title = parameters["title"] as? String
            val shortTitle = parameters["shortTitle"] as? String ?: title
            val path = parameters["path"] as? String

            if (id.isNullOrEmpty() || title.isNullOrBlank() || shortTitle.isNullOrBlank() || path == null || !ShortcutLinks.isAppPath(path)) {
                return BridgeResponse.error("INVALID_PARAMETERS", "id, title and an app path are required")
            }

            val context = activity.applicationContext
            val manager = context.getSystemService(ShortcutManager::class.java)
                ?: return BridgeResponse.error("UNSUPPORTED", "This device has no shortcut manager")

            val shortcut = ShortcutInfo.Builder(context, ShortcutLinks.ID_PREFIX + id)
                .setShortLabel(shortTitle)
                .setLongLabel(title)
                .setIcon(Icon.createWithResource(context, context.applicationInfo.icon))
                .setIntent(Intent(Intent.ACTION_VIEW, ShortcutLinks.uri(path)).setClassName(context, ShortcutLinks.TRAMPOLINE))
                .setRank(0)
                .build()

            return try {
                makeRoom(manager, shortcut.id)

                if (manager.addDynamicShortcuts(listOf(shortcut))) {
                    BridgeResponse.success()
                } else {
                    BridgeResponse.error("RATE_LIMITED", "Android is limiting shortcut updates; try again when the app is in the foreground")
                }
            } catch (e: RuntimeException) {
                BridgeResponse.error("PUSH_FAILED", e.message ?: "Android refused the shortcut")
            }
        }

        /** Drop this plugin's oldest dynamic shortcut when the launcher activity is full. */
        private fun makeRoom(manager: ShortcutManager, id: String) {
            val dynamic = manager.dynamicShortcuts.filter { it.id != id }

            if (manager.manifestShortcuts.size + dynamic.size < manager.maxShortcutCountPerActivity) {
                return
            }

            dynamic.filter { it.id.startsWith(ShortcutLinks.ID_PREFIX) }
                .maxByOrNull { it.rank }
                ?.let { manager.removeDynamicShortcuts(listOf(it.id)) }
        }
    }

    /** AppShortcuts.Remove {"id"}. A copy the user pinned stays on the home screen. */
    class Remove(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
                ?: return BridgeResponse.error("INVALID_PARAMETERS", "id is required")

            return withManager(activity) { it.removeDynamicShortcuts(listOf(ShortcutLinks.ID_PREFIX + id)) }
        }
    }

    /** AppShortcuts.TakeQueued {} -> {"invocations": [{"id", "shortcut", "at"}]}, oldest first, leaving the queue empty. */
    class TakeQueued(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> =
            BridgeResponse.success(mapOf("invocations" to ShortcutActions.takeAll(activity)))
    }

    /** AppShortcuts.Clear {}. Removes this plugin's dynamic shortcuts only. */
    class Clear(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> =
            withManager(activity) { manager ->
                val ours = manager.dynamicShortcuts.map { it.id }.filter { it.startsWith(ShortcutLinks.ID_PREFIX) }

                if (ours.isNotEmpty()) {
                    manager.removeDynamicShortcuts(ours)
                }
            }
    }

    private fun withManager(context: Context, block: (ShortcutManager) -> Unit): Map<String, Any> {
        val manager = context.applicationContext.getSystemService(ShortcutManager::class.java)
            ?: return BridgeResponse.error("UNSUPPORTED", "This device has no shortcut manager")

        return try {
            block(manager)
            BridgeResponse.success()
        } catch (e: RuntimeException) {
            BridgeResponse.error("SHORTCUTS_FAILED", e.message ?: "Android refused the change")
        }
    }
}
