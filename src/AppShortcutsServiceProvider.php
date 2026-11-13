<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts;

use FirstlightUI\AppShortcuts\Console\CompileShortcutsCommand;
use Illuminate\Support\ServiceProvider;

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

        if ($this->app->runningInConsole()) {
            $this->commands([CompileShortcutsCommand::class]);
        }
    }
}
