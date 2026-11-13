<?php

// Prints the App Intents source for shortcuts that exercise escaping, for bin/typecheck-ios.

declare(strict_types=1);

use FirstlightUI\AppShortcuts\Build\IosShortcuts;
use FirstlightUI\AppShortcuts\Shortcut;

require __DIR__.'/../../vendor/autoload.php';

final class Fixture
{
    public function __invoke(): void {}
}

echo IosShortcuts::swift([
    new Shortcut('ward-round', 'Start ward round', 'Ward round', '/ward-round', 'stethoscope', ['Start a ward round in ${applicationName}', '${applicationName} ward round']),
    new Shortcut('quoted', 'Say "hi" \\ to C:\\temp \\(x) $5 50%', 'Quoted', '/notes?q=a%20b&x="y"', null, ['Open "notes" in ${applicationName}']),
    new Shortcut('open-only', "Today's tasks", 'Tasks', '/tasks', requiresUnlock: false),
    new Shortcut('log-water', 'Log a glass of water', 'Log water', null, 'drop', ['Log water in ${applicationName}'], action: Fixture::class, reply: 'Logged "one" glass \\ 250 ml \\(ok)'),
    new Shortcut('silent', 'Silent action', 'Silent', null, requiresUnlock: false, action: Fixture::class),
]);
