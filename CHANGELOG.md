# Changelog

## Unreleased

- Shortcuts from `config/app-shortcuts.php`: App Intents and Siri phrases, Spotlight and Shortcuts-app actions, and Home Screen quick actions on iOS; static launcher shortcuts on Android. Each opens a named route.
- `AppShortcuts::push()`, `remove()` and `clear()` for long-press shortcuts added while the app runs.
- Action shortcuts: an `action` class instead of a `route` runs PHP without opening the app, booting PHP in the background when the app is closed. Siri or a toast says the string the class returns, or the configured `reply`. An action PHP can't run then is queued for the next launch. Each runs once, followed by an `ActionRan` event.
