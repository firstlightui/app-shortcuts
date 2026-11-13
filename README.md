# Firstlight App Shortcuts

App Shortcuts lets people open a screen of your NativePHP Mobile app, or run a PHP action in it, from outside the app.

- **iOS:** every shortcut is an action in Spotlight and the Shortcuts app, and works with the Action button. Up to 10 also answer Siri, with no setup by the user. Up to 4 appear when someone long-presses the app icon.
- **Android:** up to 4 appear when someone long-presses the app icon, and people can pin them to the home screen.
- **Actions:** a shortcut can run an invokable PHP class instead of opening a screen, such as logging a glass of water. On iOS, Siri and Spotlight answer without opening the app.
- **Runtime shortcuts:** add, replace and remove entries in the long-press menu while the app runs, such as recently opened screens.

A shortcut that opens a screen goes through NativePHP's deep-link path, so route middleware such as [`firstlightui/app-guard`](https://github.com/firstlightui/app-guard)'s `app-lock` runs first. Free and MIT licensed.

Composer package `firstlightui/app-shortcuts`, PHP namespace `FirstlightUI\AppShortcuts`, Kotlin package `dev.firstlightui.plugins.app_shortcuts`. Requires PHP 8.4 or later, `nativephp/mobile` ^4.7 (for Home Screen quick actions on iOS), Android 8 (API 26) or later and iOS 18 or later.

## What this does not do

- **Android voice.** Gemini replaced Google Assistant on Android phones in 2026, and its successor for app actions, AppFunctions, is not open to apps yet. Don't promise Android voice support in your app's copy.
- **Run PHP while the app is closed.** NativePHP can't boot PHP inside Siri's few seconds, and starting a second PHP runtime beside the app's is unsafe. An action used while the app is closed is queued, and runs the next time the app opens. See [Actions](#actions).
- **Answer with a result from PHP.** Siri's reply is fixed text from config, said when the action is queued, not something PHP computes.
- **Parameters filled in by Siri.** Route parameters are fixed in config or when you push the shortcut, and actions take none.

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
| `action` | Instead of `route`: an invokable class to run. See [Actions](#actions). |
| `reply` | Optional, actions only. One line that Siri says, or Android shows, once the action is queued. |
| `icon` | Optional SF Symbol for iOS. Android uses the app icon. |
| `phrases` | Optional Siri phrases, each containing `${applicationName}` once. At most 10 shortcuts may have phrases. |
| `launcher` | Optional, default `true`. Show in the long-press menu. At most 4 shortcuts may. |
| `requires_unlock` | Optional, default `true`. iOS asks the person to unlock the device before opening the app. |

These are compiled into the app, so a change takes effect on the next `php artisan native:run` or build. The build fails with a message when a shortcut breaks a platform rule, rather than the shortcut silently not appearing.

## Actions

An action runs PHP without opening a screen. Give the shortcut an invokable class instead of a route:

```php
// config/app-shortcuts.php
'log-water' => [
    'title' => 'Log a glass of water',
    'short_title' => 'Log water',
    'action' => App\Actions\LogWater::class,
    'reply' => 'Logged a glass of water',
    'icon' => 'drop',
    'phrases' => ['Log water in ${applicationName}'],
],
```

```php
namespace App\Actions;

use FirstlightUI\AppShortcuts\ActionInvocation;

final class LogWater
{
    public function __invoke(ActionInvocation $invocation): void
    {
        WaterLog::create(['drank_at' => $invocation->invokedAt]);
    }
}
```

The class is resolved from the container, so its constructor can ask for dependencies. `ActionInvocation` has the shortcut's id, a unique invocation id, and `invokedAt`, when the person used the shortcut.

When the class runs:

| Where it was used | iOS | Android |
|---|---|---|
| Siri, Spotlight, the Shortcuts app, the Action button | The app stays closed and Siri says the `reply`. PHP runs the class straight away if the app is running, otherwise the next time the app opens. | No equivalent. |
| The long-press menu | The app opens and the class runs on the screen it shows. | If the app is running, the class runs straight away, the app stays in the background and the `reply` shows as a toast. Otherwise the app opens and the class runs on its first screen. |

Because a closed app runs the class later, `invokedAt` can be minutes or days old. Record it rather than the time the class runs, and skip actions that only make sense straight away, such as starting a timer, when it is old.

Each invocation leaves the native queue before its class runs, so it runs at most once. A class that throws is reported through Laravel's exception handler and not retried. After each class that returns, an `ActionRan` event reaches the open screen's `#[On]` listeners and app-wide listeners, so a screen showing what the action changed can refresh:

```php
#[On(ActionRan::class)]
public function refresh(string $shortcutId): void
{
    $this->glasses = WaterLog::today()->count();
}
```

The queue is taken when the first native screen of a launch mounts, so actions need an app built on native screens, as Firstlight apps are. `AppShortcuts::runQueuedActions()` takes it by hand. Actions can't be pushed at runtime; `push()` opens routes only.

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
