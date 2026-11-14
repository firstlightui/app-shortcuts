<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static list<\FirstlightUI\AppShortcuts\Shortcut> configured()
 * @method static bool push(string $id, string $title, string $route, array<string, mixed> $parameters = [], ?string $icon = null, ?string $shortTitle = null)
 * @method static bool remove(string $id)
 * @method static bool clear()
 * @method static string|null run(\FirstlightUI\AppShortcuts\ActionInvocation $invocation)
 * @method static int runQueuedActions()
 *
 * @see \FirstlightUI\AppShortcuts\AppShortcuts
 */
final class AppShortcuts extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FirstlightUI\AppShortcuts\AppShortcuts::class;
    }
}
