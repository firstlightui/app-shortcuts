# Firstlight App Shortcuts

App Shortcuts lets people open a screen of your NativePHP Mobile app, or run a PHP action in it, from outside the app.

- **iOS:** every shortcut is an action in Spotlight and the Shortcuts app, and works with the Action button. Up to 10 also answer Siri, with no setup by the user. Up to 4 appear when someone long-presses the app icon.
- **Android:** up to 4 appear when someone long-presses the app icon, and people can pin them to the home screen.
- **Actions:** a shortcut can run an invokable PHP class instead of opening a screen, such as logging a glass of water. PHP runs without the app opening, even when it was closed, and Siri or a toast says what the class returns.
- **Runtime shortcuts:** add, replace and remove entries in the long-press menu while the app runs, such as recently opened screens.

A shortcut that opens a screen goes through NativePHP's deep-link path, so route middleware such as [`firstlightui/app-guard`](https://github.com/firstlightui/app-guard)'s `app-lock` runs first. Free and MIT licensed.

Composer package `firstlightui/app-shortcuts`, PHP namespace `FirstlightUI\AppShortcuts`, Kotlin package `dev.firstlightui.plugins.app_shortcuts`. Requires PHP 8.4 or later, `nativephp/mobile` ^4.7 (for Home Screen quick actions on iOS), Android 8 (API 26) or later and iOS 18 or later.

## What this does not do

- **Android voice.** Gemini replaced Google Assistant on Android phones in 2026, and its successor for app actions, AppFunctions, is not open to apps yet. Don't promise Android voice support in your app's copy.
- **Always run an action straight away.** When PHP can't start in the background, such as in a debug build on iOS or just after an over-the-air update, the action is queued and runs the next time the app opens. See [Actions](#actions).
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
| `reply` | Optional, actions only. What Siri says, or Android shows, when the class returns no text or the action is queued. |
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
    public function __invoke(ActionInvocation $invocation): string
    {
        WaterLog::create(['drank_at' => $invocation->invokedAt]);

        return 'That is '.WaterLog::today()->count().' glasses today.';
    }
}
```

The class is resolved from the container, so its constructor can ask for dependencies. `ActionInvocation` has the shortcut's id, a unique invocation id, and `invokedAt`, when the person used the shortcut. A string the class returns is the reply, as one line; return nothing to use the configured `reply`.

When the class runs:

| Where it was used | iOS | Android |
|---|---|---|
| Siri, Spotlight, the Shortcuts app, the Action button | The class runs straight away and Siri says its reply. The app stays out of sight, even when it was closed: iOS starts it in the background and the plugin boots PHP there, in about two seconds. | No equivalent. |
| The long-press menu | The app opens and the class runs on the screen it shows. | The class runs straight away in the background, whether or not the app was running, and its reply shows as a toast. |

On Android 13 and later, a toast from an app with no window on screen shows only if the app may post notifications, so the plugin declares `POST_NOTIFICATIONS`. The first action anyone runs asks for it, once, and the action runs while the prompt is up. Whenever notifications are off, the long-press menu shows a spinner until the reply is ready, so the toast still shows. An app that asks for notifications itself, for push, is never asked again.

PHP is the app's own, and boots once. If the app opens while an action is booting PHP, its splash screen waits a moment and then finds PHP running. An action used while the app is starting waits for the app's PHP instead of booting a second one.

The action is queued instead, and runs on the first screen of the next launch, when PHP can't start in the background:

- On iOS, in a debug build, which unpacks its Laravel bundle at every launch, and after an over-the-air update until the app has opened once, since unpacking needs setup NativePHP keeps private.
- In an app using NativePHP's classic runtime mode.
- When PHP fails to boot, or the app takes more than about 10 seconds to boot it. On Android the app then opens to run it.

So `invokedAt` can occasionally be minutes or days old. Record it rather than the time the class runs, and skip actions that only make sense straight away, such as starting a timer, when it is old.

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
