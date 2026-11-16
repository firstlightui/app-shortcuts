package dev.firstlightui.plugins.app_shortcuts

import android.Manifest
import android.app.Activity
import android.app.NotificationManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.ContextThemeWrapper
import android.view.Gravity
import android.widget.FrameLayout
import android.widget.ProgressBar
import android.widget.Toast
import java.util.UUID

/**
 * Every shortcut opens this invisible activity, which hands a route's link to MainActivity, or runs an
 * action, and finishes.
 *
 * An action runs in PHP on a background thread while the person stays where they were, and its reply
 * shows as a toast. If PHP can't start, the app opens and runs the queued action on its first screen.
 *
 * From Android 13, a toast from an app with no window on screen shows only if the app may post
 * notifications. So the first action asks for that permission, once, and the activity stays until the
 * person answers. Whenever notifications are off, it stays with a spinner until PHP replies, so the
 * toast still shows; otherwise it finishes at once and the toast follows.
 *
 * An action's run outlives the activity: a rotation while the prompt is up recreates it, and the new
 * instance takes the run over, waits for the answer and shows the reply.
 *
 * Android starts a static shortcut with FLAG_ACTIVITY_CLEAR_TASK. Pointed at MainActivity, that would
 * destroy the running activity and the screens PHP is drawing. This activity has its own task, so only
 * that task is cleared; CLEAR_TOP with SINGLE_TOP then delivers the link to a running MainActivity
 * through onNewIntent, and a cold start goes through onCreate as for any deep link.
 */
class ShortcutTrampolineActivity : Activity() {
    private var run: PendingRun? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (savedInstanceState != null) {
            // Recreated: take over the run this activity started. Gone once its reply has shown.
            val restored = savedInstanceState.getString(RUN_KEY)?.let(runs::get)

            if (restored == null) {
                finish()
            } else {
                attach(restored)
            }

            return
        }

        val data = intent?.data
        val action = ShortcutLinks.actionId(data)

        if (action == null) {
            ShortcutLinks.mainActivityIntent(this, data)?.let(::startActivity)
            finish()
            return
        }

        val pending = PendingRun(UUID.randomUUID().toString(), ShortcutLinks.actionReply(data))
        runs[pending.token] = pending
        start(applicationContext, pending, action)

        if (shouldAskForNotifications()) {
            pending.asking = true
            markAskedForNotifications()
            requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), NOTIFICATIONS_REQUEST)
        }

        attach(pending)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        if (requestCode != NOTIFICATIONS_REQUEST) {
            return
        }

        run?.asking = false
        settle()
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        run?.let { outState.putString(RUN_KEY, it.token) }
    }

    override fun onDestroy() {
        run?.let { if (it.host === this) it.host = null }
        super.onDestroy()
    }

    private fun attach(pending: PendingRun) {
        run = pending
        pending.host = this
        settle()
    }

    /** Waits for the prompt's answer or for PHP, or shows the reply and finishes. */
    private fun settle() {
        val pending = run ?: return
        val result = pending.outcome

        when {
            // The system answers through onRequestPermissionsResult, to whichever instance is current.
            pending.asking -> Unit

            // Still on screen, so the toast shows whether or not notifications are on.
            result != null -> {
                complete(applicationContext, pending, result)
                finish()
            }

            // The toast shows without a window, so leave now and let it follow.
            notificationsEnabled() -> {
                pending.host = null
                finish()
            }

            else -> showSpinner()
        }
    }

    private fun showSpinner() {
        val themed = ContextThemeWrapper(this, android.R.style.Theme_DeviceDefault)

        setContentView(
            FrameLayout(this).apply {
                addView(ProgressBar(themed), FrameLayout.LayoutParams(
                    FrameLayout.LayoutParams.WRAP_CONTENT,
                    FrameLayout.LayoutParams.WRAP_CONTENT,
                    Gravity.CENTER,
                ))
            },
        )
    }

    private fun notificationsEnabled(): Boolean =
        getSystemService(NotificationManager::class.java)?.areNotificationsEnabled() ?: true

    /** Only from Android 13, which added the permission, and only for the first action ever run. */
    private fun shouldAskForNotifications(): Boolean =
        Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU
            && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
            && !preferences().getBoolean(ASKED_KEY, false)

    private fun markAskedForNotifications() {
        preferences().edit().putBoolean(ASKED_KEY, true).apply()
    }

    private fun preferences() = applicationContext.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE)

    /** One action's run, kept apart from the activity that started it. Used on the main thread only. */
    private class PendingRun(val token: String, val fallbackReply: String?) {
        var host: ShortcutTrampolineActivity? = null
        var asking = false
        var outcome: ShortcutActions.Outcome? = null
    }

    private companion object {
        const val NOTIFICATIONS_REQUEST = 1
        const val RUN_KEY = "app_shortcuts_run"
        const val PREFERENCES = "firstlight_app_shortcuts_trampoline"
        const val ASKED_KEY = "asked_notifications"

        /** Runs whose reply hasn't shown yet, by token. */
        val runs = HashMap<String, PendingRun>()

        fun start(context: Context, pending: PendingRun, action: String) {
            val id = ShortcutActions.enqueue(context, action)
            val main = Handler(Looper.getMainLooper())

            Thread({
                val result = ShortcutActions.run(context, id)

                main.post { finished(context, pending, result) }
            }, "app-shortcuts-action").start()
        }

        fun finished(context: Context, pending: PendingRun, result: ShortcutActions.Outcome) {
            pending.outcome = result
            val host = pending.host

            when {
                host != null && !host.isFinishing -> host.settle()

                // Recreated while the prompt is up: the new instance shows it once the person answers.
                pending.asking -> Unit

                // Already gone: with notifications on the toast shows without a window.
                else -> complete(context, pending, result)
            }
        }

        fun complete(context: Context, pending: PendingRun, result: ShortcutActions.Outcome) {
            runs.remove(pending.token)

            when (result) {
                is ShortcutActions.Outcome.Ran -> (result.reply ?: pending.fallbackReply)
                    ?.let { Toast.makeText(context, it, Toast.LENGTH_SHORT).show() }

                ShortcutActions.Outcome.Failed ->
                    Toast.makeText(context, "That didn't work. Open the app to try again.", Toast.LENGTH_LONG).show()

                // Android lets an app start an activity this soon after its own was visible.
                ShortcutActions.Outcome.Queued -> try {
                    context.startActivity(ShortcutLinks.openAppIntent(context))
                } catch (e: RuntimeException) {
                    Toast.makeText(context, "Open the app to finish", Toast.LENGTH_LONG).show()
                }
            }
        }
    }
}
