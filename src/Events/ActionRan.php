<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Native\Mobile\Events\Concerns\BroadcastsGlobally;

/**
 * A shortcut action's class ran without throwing. Reaches the open screen's #[On] listeners and
 * app-wide listeners, so a screen showing what the action changed can refresh.
 */
final class ActionRan implements BroadcastsGlobally
{
    use Dispatchable;

    public function __construct(
        public string $shortcutId,
        public string $invocationId,
    ) {}
}
