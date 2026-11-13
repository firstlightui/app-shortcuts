# Changelog

## Unreleased

- Shortcuts from `config/app-shortcuts.php`: App Intents and Siri phrases, Spotlight and Shortcuts-app actions, and Home Screen quick actions on iOS; static launcher shortcuts on Android. Each opens a named route.
- `AppShortcuts::push()`, `remove()` and `clear()` for long-press shortcuts added while the app runs.
- Action shortcuts: an `action` class instead of a `route` runs PHP without opening a screen, with an optional `reply` that Siri speaks. Actions queue natively and run once, when PHP is running or the next time the app opens, followed by an `ActionRan` event.
