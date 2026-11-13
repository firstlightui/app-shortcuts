<?php

/*
| Shortcuts built into the app. Each one opens a named route or runs an action: an
| invokable class that receives a FirstlightUI\AppShortcuts\ActionInvocation.
|
| iOS:     every shortcut is an action in Spotlight and the Shortcuts app. Up to 10 with
|          `phrases` also answer Siri with no setup, and a phrase must contain
|          ${applicationName}. Shortcuts with `launcher` appear when someone long-presses
|          the app icon.
| Android: shortcuts with `launcher` appear when someone long-presses the app icon, and
|          can be pinned to the home screen. Android ignores `phrases` and `icon`.
|
| Actions: iOS answers Siri and Spotlight without opening the app, and PHP runs the
| class straight away if the app is running, otherwise the next time it opens. On
| Android, an action runs straight away if the app is running and opens the app if not.
|
| Changes take effect on the next `php artisan native:run` or build: the platforms
| read these from the app binary. Keep titles free of personal or patient details;
| they show on a locked device and in search.
*/

return [
    'shortcuts' => [
        // 'new-task' => [
        //     'title' => 'New task',
        //     'short_title' => 'New task',     // optional; the launcher shows it when space is short
        //     'route' => 'tasks.create',       // a named route
        //     'parameters' => [],              // optional route parameters
        //     'icon' => 'plus.circle',         // optional SF Symbol for iOS
        //     'phrases' => ['Add a task in ${applicationName}'],
        //     'launcher' => true,              // optional, default true; at most 4
        //     'requires_unlock' => true,       // optional, default true; iOS asks to unlock first
        // ],
        // 'log-water' => [
        //     'title' => 'Log a glass of water',
        //     'action' => App\Actions\LogWater::class, // instead of route
        //     'reply' => 'Logged a glass of water',     // optional; Siri says it, Android shows it
        //     'phrases' => ['Log water in ${applicationName}'],
        // ],
    ],
];
