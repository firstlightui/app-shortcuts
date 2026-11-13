package dev.firstlightui.plugins.app_shortcuts

import android.app.Activity
import android.content.Intent
import android.os.Bundle
import android.widget.Toast

/**
 * Every shortcut opens this invisible activity, which hands a route's link to MainActivity, or queues
 * an action, and finishes.
 *
 * An action runs where the app is: when a native screen is live, PHP takes the queue straight away and
 * the person stays where they were, shown the action's reply; otherwise the app opens, and PHP takes
 * the queue once its first screen mounts.
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
            ShortcutActions.enqueue(this, action)

            if (ShortcutActions.wakePhp()) {
                ShortcutLinks.actionReply(data)?.let { Toast.makeText(applicationContext, it, Toast.LENGTH_SHORT).show() }
            } else {
                startActivity(ShortcutLinks.openAppIntent(this))
            }
        }

        finish()
    }
}
