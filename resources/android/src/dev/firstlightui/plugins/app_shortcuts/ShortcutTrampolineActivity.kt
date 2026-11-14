package dev.firstlightui.plugins.app_shortcuts

import android.app.Activity
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.widget.Toast

/**
 * Every shortcut opens this invisible activity, which hands a route's link to MainActivity, or runs an
 * action, and finishes.
 *
 * An action runs in PHP on a background thread while the person stays where they were, and its reply
 * shows as a toast. If PHP can't start, the app opens and runs the queued action on its first screen.
 *
 * Android starts a static shortcut with FLAG_ACTIVITY_CLEAR_TASK. Pointed at MainActivity, that would
 * destroy the running activity and the screens PHP is drawing. This activity has its own task, so only
 * that task is cleared; CLEAR_TOP with SINGLE_TOP then delivers the link to a running MainActivity
 * through onNewIntent, and a cold start goes through onCreate as for any deep link.
 */
class ShortcutTrampolineActivity : Activity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val data = intent?.data
        val action = ShortcutLinks.actionId(data)

        if (action == null) {
            ShortcutLinks.mainActivityIntent(this, data)?.let(::startActivity)
        } else {
            run(action, ShortcutLinks.actionReply(data))
        }

        finish()
    }

    private fun run(action: String, fallbackReply: String?) {
        val context = applicationContext
        val id = ShortcutActions.enqueue(context, action)
        val main = Handler(Looper.getMainLooper())

        Thread({
            val outcome = ShortcutActions.run(context, id)

            main.post {
                when (outcome) {
                    is ShortcutActions.Outcome.Ran -> (outcome.reply ?: fallbackReply)
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
        }, "app-shortcuts-action").start()
    }
}
