package dev.firstlightui.plugins.app_shortcuts

import android.content.Context
import android.util.Log
import com.nativephp.mobile.bridge.LaravelEnvironment
import com.nativephp.mobile.bridge.PHPBridge
import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID

/**
 * Runs shortcut actions in PHP without opening the app, and keeps the queue of those PHP has yet to
 * run, matching resources/ios/Sources/AppShortcutsActions.swift.
 *
 * An action runs in the app's own PHP, booted here when the app is closed. NativePHP boots it under a
 * process-wide lock and reuses a runtime that is already up, so this is boot or wait in both
 * directions: an app opened while an action boots PHP waits on the lock and then finds PHP running,
 * and an action used while the app boots waits for the app's PHP. The runtime then stays up, as
 * NativePHP leaves it when a plugin's foreground service outlives the activity, for the app to reuse.
 *
 * The action itself runs in NativePHP's ephemeral runtime, a PHP context on this thread beside the
 * app's: a live native screen keeps the app's PHP thread busy with its run loop.
 *
 * The trampoline queues an action before running it, and run() takes it back out just before PHP
 * starts on it. If Android ends the process first, the action is still queued, and PHP takes the
 * queue through AppShortcuts.TakeQueued when the app next opens. Either way it runs at most once.
 *
 * The queue is written on the main thread, a run thread and the PHP thread, so each change holds
 * this object's lock. commit() rather than apply(), so an entry is on disk when the call returns.
 */
object ShortcutActions {
    private const val TAG = "AppShortcuts"

    /** Matches RunActionCommand::RESULT_PREFIX in PHP. */
    private const val RESULT_PREFIX = "app-shortcuts-result:"

    private const val PREFERENCES = "firstlight_app_shortcuts"
    private const val KEY = "queued_actions"

    /** The oldest entries are dropped past this, so a queue nobody drains cannot grow without end. */
    private const val LIMIT = 100

    private val runLock = Any()

    sealed interface Outcome {
        /** The action's class ran; reply is what PHP or config says to show. */
        data class Ran(val reply: String?) : Outcome

        /** The action's class threw. It is not retried. */
        data object Failed : Outcome

        /** PHP could not start, so the action stays queued for the next time the app opens. */
        data object Queued : Outcome
    }

    /** Queues one run of an action and returns its invocation id. */
    @Synchronized
    fun enqueue(context: Context, shortcut: String): String {
        val id = UUID.randomUUID().toString()
        val entries = read(context)
        entries.put(JSONObject().put("id", id).put("shortcut", shortcut).put("at", System.currentTimeMillis()))

        while (entries.length() > LIMIT) {
            entries.remove(0)
        }

        save(context, entries)

        return id
    }

    /**
     * Runs a queued invocation now, on the calling thread, which must not be the main thread.
     * Booting PHP with the app closed takes a second or more.
     */
    fun run(context: Context, id: String): Outcome = synchronized(runLock) {
        val appContext = context.applicationContext
        val bridge = PHPBridge(appContext)

        // A classic-mode app runs PHP per request and never boots the persistent runtime.
        if (LaravelEnvironment.getRuntimeMode(appContext) == "classic") {
            return Outcome.Queued
        }

        val booted = try {
            // With the app closed, this sets up PHP's environment and unpacks the Laravel bundle
            // if needed, as NativePHP does for its own background work. A no-op when PHP is up.
            LaravelEnvironment(appContext).initializeForBackground()
            bridge.bootPersistentRuntime()
        } catch (e: RuntimeException) {
            Log.e(TAG, "Could not start PHP for a shortcut action", e)
            false
        }

        if (!booted) {
            return Outcome.Queued
        }

        if (bridge.nativeEphemeralBoot(bridge.getLaravelPath() + "/vendor/nativephp/mobile/bootstrap/android/persistent.php") != 0) {
            return Outcome.Queued
        }

        val output = try {
            // Taken out only now, so it is still queued if anything above failed or the process ends.
            val entry = claim(appContext, id) ?: return Outcome.Ran(null)

            bridge.nativeEphemeralArtisan(
                "app-shortcuts:run --shortcut=${entry.getString("shortcut")} --id=${entry.getString("id")} --at=${entry.getLong("at")}",
            )
        } finally {
            // Ends only this thread's PHP context; the app's runtime stays up.
            bridge.nativeEphemeralShutdown()
        }

        val result = output.lineSequence().lastOrNull { it.startsWith(RESULT_PREFIX) }
            ?.let { runCatching { JSONObject(it.removePrefix(RESULT_PREFIX)) }.getOrNull() }

        if (result?.optBoolean("ran") != true) {
            Log.w(TAG, "Shortcut action failed: ${output.takeLast(300)}")
            return Outcome.Failed
        }

        Outcome.Ran(result.optString("reply").takeIf { !result.isNull("reply") && it.isNotBlank() })
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

    /** Takes one entry out of the queue, or null when PHP has already taken it. */
    @Synchronized
    private fun claim(context: Context, id: String): JSONObject? {
        val entries = read(context)
        val index = (0 until entries.length()).firstOrNull { entries.optJSONObject(it)?.optString("id") == id }
            ?: return null

        val entry = entries.getJSONObject(index)
        entries.remove(index)
        save(context, entries)

        return entry
    }

    private fun read(context: Context): JSONArray = try {
        JSONArray(preferences(context).getString(KEY, null) ?: "[]")
    } catch (e: org.json.JSONException) {
        JSONArray()
    }

    private fun save(context: Context, entries: JSONArray) {
        preferences(context).edit().putString(KEY, entries.toString()).commit()
    }

    private fun preferences(context: Context) =
        context.applicationContext.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE)
}
