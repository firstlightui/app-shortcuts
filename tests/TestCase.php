<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts\Tests;

use FirstlightUI\AppShortcuts\AppShortcutsServiceProvider;
use Illuminate\Support\Facades\Route;
use Native\Mobile\NativeServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NativeServiceProvider::class,
            AppShortcutsServiceProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        Route::get('/ward-round', fn () => '')->name('ward-round');
        Route::get('/tasks/create', fn () => '')->name('tasks.create');
        Route::get('/patients/{patient}', fn () => '')->name('patients.show');
    }
}
