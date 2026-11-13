# Firstlight App Shortcuts

App Shortcuts lets people open a screen of your NativePHP Mobile app without opening the app first.

- **iOS:** every shortcut is an action in Spotlight and the Shortcuts app, and works with the Action button. Up to 10 also answer Siri, with no setup by the user. Up to 4 appear when someone long-presses the app icon.
- **Android:** up to 4 appear when someone long-presses the app icon, and people can pin them to the home screen.
- **Runtime shortcuts:** add, replace and remove entries in the long-press menu while the app runs, such as recently opened screens.

Each shortcut opens a named route through NativePHP's deep-link path, so route middleware such as [`firstlightui/app-guard`](https://github.com/firstlightui/app-guard)'s `app-lock` runs first. Free and MIT licensed.

Composer package `firstlightui/app-shortcuts`, PHP namespace `FirstlightUI\AppShortcuts`, Kotlin package `dev.firstlightui.plugins.app_shortcuts`. Requires PHP 8.4 or later, `nativephp/mobile` ^4.7 (for Home Screen quick actions on iOS), Android 8 (API 26) or later and iOS 18 or later.

## What this does not do

- **Android voice.** Gemini replaced Google Assistant on Android phones in 2026, and its successor for app actions, AppFunctions, is not open to apps yet. Don't promise Android voice support in your app's copy.
- **Run without opening the app.** Every shortcut opens the app on its screen. A shortcut that "does something" opens a screen that does it.
- **Parameters filled in by Siri.** Route parameters are fixed in config or when you push the shortcut.

## Install

```bash
composer require firstlightui/app-shortcuts
php artisan native:plugin:register firstlightui/app-shortcuts
php artisan vendor:publish --tag=app-shortcuts-config
```

## Shortcuts built into the app

```php
// config/app-shortcuts.php
'shortcuts' => [
    'ward-round' => [
        'title' => 'Start ward round',
        'short_title' => 'Ward round',
        'route' => 'ward-round',
        'icon' => 'stethoscope',
        'phrases' => ['Start a ward round in ${applicationName}'],
    ],
    'new-task' => [
        'title' => 'New task',
        'route' => 'tasks.create',
        'launcher' => false,
    ],
],
```

| Key | Meaning |
|---|---|
| id (the array key) | Lowercase letters and digits joined by hyphens. Keep it stable: users' own automations in the Shortcuts app refer to it. |
| `title` | Shown in Spotlight, the Shortcuts app and the long-press menu. |
| `short_title` | Optional. Shown when space is short. Defaults to `title`. |
| `route`, `parameters` | The named route to open, and optional route parameters. Unknown routes fail the build. |
| `icon` | Optional SF Symbol for iOS. Android uses the app icon. |
| `phrases` | Optional Siri phrases, each containing `${applicationName}` once. At most 10 shortcuts may have phrases. |
| `launcher` | Optional, default `true`. Show in the long-press menu. At most 4 shortcuts may. |
| `requires_unlock` | Optional, default `true`. iOS asks the person to unlock the device before opening the app. |

These are compiled into the app, so a change takes effect on the next `php artisan native:run` or build. The build fails with a message when a shortcut breaks a platform rule, rather than the shortcut silently not appearing.

## Shortcuts added at runtime

```php
use FirstlightUI\AppShortcuts\Facades\AppShortcuts;

AppShortcuts::push('triage', 'Triage list', 'triage.index');   // newest first
AppShortcuts::remove('triage');
AppShortcuts::clear();                                          // for example on sign-out
```

Each method returns `true` when the native side applied the change, and `false` when the bridge function is missing (no native build, or the plugin is not registered) or reported an error. Runtime shortcuts appear in the long-press menu only: iOS can't add Siri phrases at runtime. A shortcut the user pinned on Android stays on the home screen after `remove()`.

## Privacy

Titles show on a locked device, in search and in the long-press menu. Keep them generic, such as "Triage list", never a patient's or customer's name. Leave `requires_unlock` on for anything behind a sign-in.

## Development

```bash
composer test && composer lint && composer analyse
resources/android/gradlew -p resources/android compileHostCheckKotlin   # needs ANDROID_HOME with platform 36
bin/typecheck-ios                                                       # needs Xcode
```
