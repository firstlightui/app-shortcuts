<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Native\Mobile\Events\Concerns\BroadcastsGlobally;

/**
 * The native side queued a shortcut action while PHP was running. The service provider answers it by
 * running the queue; apps listen for ActionRan instead.
 *
 * @internal
 */
final class ActionsQueued implements BroadcastsGlobally
{
    use Dispatchable;
}
