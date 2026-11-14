<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts\Tests\Fixtures;

use FirstlightUI\AppShortcuts\ActionInvocation;
use RuntimeException;

/** A shortcut action for tests: records each invocation, returns a set value, and throws for one shortcut id. */
final class RecordingAction
{
    /** @var list<ActionInvocation> */
    public static array $ran = [];

    public static ?string $failFor = null;

    /** What the action returns, which becomes the reply when it is a non-empty string. */
    public static mixed $returns = null;

    public function __invoke(ActionInvocation $invocation): mixed
    {
        if ($invocation->shortcutId === self::$failFor) {
            throw new RuntimeException("Action {$invocation->shortcutId} failed");
        }

        self::$ran[] = $invocation;

        return self::$returns;
    }
}
