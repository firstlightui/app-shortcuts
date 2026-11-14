<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts;

use FirstlightUI\AppShortcuts\Console\CompileShortcutsCommand;
use FirstlightUI\AppShortcuts\Console\RunActionCommand;
use FirstlightUI\AppShortcuts\Events\ActionsQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Events\Screen\ScreenMounted;

final class AppShortcutsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/app-shortcuts.php', 'app-shortcuts');

        $this->app->singleton(AppShortcuts::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/app-shortcuts.php' => config_path('app-shortcuts.php'),
        ], 'app-shortcuts-config');

        // Actions queued while PHP was not running, such as from Siri with the app closed, run once
        // the first screen of a launch is up. Listening on the native router's own event keeps
        // queue workers and other lanes, which boot the app too, from taking the queue.
        $launched = false;

        Event::listen(ScreenMounted::class, function () use (&$launched): void {
            if (! $launched) {
                $launched = true;
                $this->app->make(AppShortcuts::class)->runQueuedActions();
            }
        });

        Event::listen(ActionsQueued::class, function (): void {
            $this->app->make(AppShortcuts::class)->runQueuedActions();
        });

        // The native side runs app-shortcuts:run through NativePHP's in-app runtimes, which are not
        // always marked as running in the console, so it is registered everywhere.
        $this->commands([RunActionCommand::class]);

        // Symfony Console reads PHP_SELF as it starts. NativePHP 4.7's persistent artisan on iOS does
        // not set it, and only a web request does, so a shortcut that boots PHP cold would fail.
        $_SERVER['PHP_SELF'] ??= 'artisan.php';

        if ($this->app->runningInConsole()) {
            $this->commands([CompileShortcutsCommand::class]);
        }
    }
}
