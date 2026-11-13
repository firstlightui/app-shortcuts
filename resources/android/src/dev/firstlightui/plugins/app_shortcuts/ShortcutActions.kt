package dev.firstlightui.plugins.app_shortcuts

import android.content.Context
import com.nativephp.mobile.ui.nativerender.NativeElementBridge
import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID

/**
 * The queue of shortcut actions waiting for PHP, matching resources/ios/Sources/AppShortcutsActions.swift.
 *
 * The trampoline writes on the main thread and AppShortcuts.TakeQueued reads on the PHP thread, so
 * both hold this object's lock. commit() rather than apply(), so an entry is on disk before the
 * trampoline reports it queued.
 */
object ShortcutActions {
    /** Matches FirstlightUI\AppShortcuts\Events\ActionsQueued in PHP. */
    const val QUEUED_EVENT = "FirstlightUI\\AppShortcuts\\Events\\ActionsQueued"

    private const val PREFERENCES = "firstlight_app_shortcuts"
    private const val KEY = "queued_actions"

    /** The oldest entries are dropped past this, so a queue nobody drains cannot grow without end. */
    private const val LIMIT = 100

    @Synchronized
    fun enqueue(context: Context, shortcut: String) {
        val entries = read(context)
        entries.put(
            JSONObject()
                .put("id", UUID.randomUUID().toString())
                .put("shortcut", shortcut)
                .put("at", System.currentTimeMillis()),
        )

        while (entries.length() > LIMIT) {
            entries.remove(0)
        }

        preferences(context).edit().putString(KEY, entries.toString()).commit()
    }

    /** Every queued entry, oldest first, leaving the queue empty. */
    @Synchronized
    fun takeAll(context: Context): List<Map<String, Any>> {
        val entries = read(context)

        if (entries.length() > 0) {
            preferences(context).edit().remove(KEY).commit()
        }

        return (0 until entries.length()).mapNotNull { index ->
            val entry = entries.optJSONObject(index) ?: return@mapNotNull null
            mapOf(
                "id" to entry.optString("id"),
                "shortcut" to entry.optString("shortcut"),
                "at" to entry.optLong("at"),
            )
        }
    }

    /**
     * Tells a live native screen that actions are waiting. False when no native screen session is
     * live, such as before PHP has booted: the app has to open for the action to run.
     */
    fun wakePhp(): Boolean = try {
        NativeElementBridge.postNativeEventToQueue(QUEUED_EVENT, "{}")
    } catch (e: LinkageError) {
        false
    }

    private fun read(context: Context): JSONArray = try {
        JSONArray(preferences(context).getString(KEY, null) ?: "[]")
    } catch (e: org.json.JSONException) {
        JSONArray()
    }

    private fun preferences(context: Context) =
        context.applicationContext.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE)
}
