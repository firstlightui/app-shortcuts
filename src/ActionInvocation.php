<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts;

use Carbon\CarbonImmutable;

/**
 * One run of a shortcut action, handed to the action's class.
 *
 * $invokedAt is when the person used the shortcut, which can be well before PHP runs it: an action
 * used while the app was closed runs the next time the app opens. An action that only makes sense
 * straight away, such as starting a timer, should check it. $id is unique per invocation, for an
 * action that must not repeat its effect.
 */
final readonly class ActionInvocation
{
    public function __construct(
        public string $id,
        public string $shortcutId,
        public CarbonImmutable $invokedAt,
    ) {}
}
