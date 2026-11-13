<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts\Tests\Fixtures;

use FirstlightUI\AppShortcuts\ActionInvocation;
use RuntimeException;

/** A shortcut action for tests: records each invocation, and throws for one shortcut id. */
final class RecordingAction
{
    /** @var list<ActionInvocation> */
    public static array $ran = [];

    public static ?string $failFor = null;

    public function __invoke(ActionInvocation $invocation): void
    {
        if ($invocation->shortcutId === self::$failFor) {
            throw new RuntimeException("Action {$invocation->shortcutId} failed");
        }

        self::$ran[] = $invocation;
    }
}
