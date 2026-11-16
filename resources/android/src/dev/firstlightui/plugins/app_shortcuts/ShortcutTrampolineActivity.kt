package dev.firstlightui.plugins.app_shortcuts

import android.Manifest
import android.app.Activity
import android.app.NotificationManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.view.Gravity
import android.widget.FrameLayout
import android.widget.ProgressBar
import android.widget.Toast

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
 * Android starts a static shortcut with FLAG_ACTIVITY_CLEAR_TASK. Pointed at MainActivity, that would
 * destroy the running activity and the screens PHP is drawing. This activity has its own task, so only
 * that task is cleared; CLEAR_TOP with SINGLE_TOP then delivers the link to a running MainActivity
 * through onNewIntent, and a cold start goes through onCreate as for any deep link.
 */
class ShortcutTrampolineActivity : Activity() {
    private var fallbackReply: String? = null
    private var outcome: ShortcutActions.Outcome? = null
    private var asking = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Recreated, say by a rotation: the action already started, and its reply still shows.
        if (savedInstanceState != null) {
            finish()
            return
        }

        val data = intent?.data
        val action = ShortcutLinks.actionId(data)

        if (action == null) {
            ShortcutLinks.mainActivityIntent(this, data)?.let(::startActivity)
            finish()
            return
        }

        fallbackReply = ShortcutLinks.actionReply(data)
        run(action)

        when {
            shouldAskForNotifications() -> {
                asking = true
                markAskedForNotifications()
                requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), NOTIFICATIONS_REQUEST)
            }

            notificationsEnabled() -> finish()

            else -> showSpinner()
        }
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        if (requestCode != NOTIFICATIONS_REQUEST) {
            return
        }

        asking = false

        val ran = outcome

        when {
            ran != null -> {
                show(ran)
                finish()
            }

            notificationsEnabled() -> finish()

            else -> showSpinner()
        }
    }

    private fun run(action: String) {
        val context = applicationContext
        val id = ShortcutActions.enqueue(context, action)

        Thread({
            val result = ShortcutActions.run(context, id)

            runOnUiThread { onOutcome(result) }
        }, "app-shortcuts-action").start()
    }

    private fun onOutcome(result: ShortcutActions.Outcome) {
        // Already gone: notifications are on, so the toast shows without a window.
        if (isFinishing || isDestroyed) {
            show(result)
            return
        }

        // The permission prompt is up; the reply shows once the person answers it.
        if (asking) {
            outcome = result
            return
        }

        // Still on screen, so the toast shows whether or not notifications are on.
        show(result)
        finish()
    }

    private fun show(result: ShortcutActions.Outcome) {
        val context = applicationContext

        when (result) {
            is ShortcutActions.Outcome.Ran -> (result.reply ?: fallbackReply)
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

    private fun showSpinner() {
        setContentView(
            FrameLayout(this).apply {
                addView(ProgressBar(context), FrameLayout.LayoutParams(
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

    private companion object {
        const val NOTIFICATIONS_REQUEST = 1
        const val PREFERENCES = "firstlight_app_shortcuts_trampoline"
        const val ASKED_KEY = "asked_notifications"
    }
}
