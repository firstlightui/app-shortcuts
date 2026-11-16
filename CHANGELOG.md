# Changelog

## Unreleased

- Android: the spinner a shortcut shows while notifications are off is white on a dark rounded card, so it shows over a light wallpaper and the open shortcut menu. It was a pale ring with no background.

## 1.0.0-alpha1 - 2026-11-16

- Shortcuts from `config/app-shortcuts.php`: App Intents and Siri phrases, Spotlight and Shortcuts-app actions, and Home Screen quick actions on iOS; static launcher shortcuts on Android. Each opens a named route.
- `AppShortcuts::push()`, `remove()` and `clear()` for long-press shortcuts added while the app runs.
- Action shortcuts: an `action` class instead of a `route` runs PHP without opening the app, booting PHP in the background when the app is closed. Siri or a toast says the string the class returns, or the configured `reply`. If the app opens meanwhile it waits for that boot, and an action used while the app starts waits for the app's. An action PHP can't run then is queued for the next launch. Each runs once, followed by an `ActionRan` event.
- Android: declares `POST_NOTIFICATIONS` and asks for it on the first action, because Android 13 and later hide toasts from an app with no window unless it may post notifications. While notifications are off, the shortcut shows a spinner until the reply is ready, so the toast still shows.
