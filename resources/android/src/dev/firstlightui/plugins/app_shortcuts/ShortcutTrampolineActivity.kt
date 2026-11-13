package dev.firstlightui.plugins.app_shortcuts

import android.app.Activity
import android.content.Intent
import android.os.Bundle

/**
 * Every shortcut opens this invisible activity, which hands the link to MainActivity and finishes.
 *
 * Android starts a static shortcut with FLAG_ACTIVITY_CLEAR_TASK. Pointed at MainActivity, that would
 * destroy the running activity and the screens PHP is drawing. This activity has its own task, so only
 * that task is cleared; CLEAR_TOP with SINGLE_TOP then delivers the link to a running MainActivity
 * through onNewIntent, and a cold start goes through onCreate as for any deep link.
 */
class ShortcutTrampolineActivity : Activity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        ShortcutLinks.mainActivityIntent(this, intent?.data)?.let(::startActivity)
        finish()
    }
}
