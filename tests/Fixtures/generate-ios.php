<?php

// Prints the App Intents source for shortcuts that exercise escaping, for bin/typecheck-ios.

declare(strict_types=1);

use FirstlightUI\AppShortcuts\Build\IosShortcuts;
use FirstlightUI\AppShortcuts\Shortcut;

require __DIR__.'/../../vendor/autoload.php';

echo IosShortcuts::swift([
    new Shortcut('ward-round', 'Start ward round', 'Ward round', '/ward-round', 'stethoscope', ['Start a ward round in ${applicationName}', '${applicationName} ward round']),
    new Shortcut('quoted', 'Say "hi" \\ to C:\\temp \\(x) $5 50%', 'Quoted', '/notes?q=a%20b&x="y"', null, ['Open "notes" in ${applicationName}']),
    new Shortcut('open-only', "Today's tasks", 'Tasks', '/tasks', requiresUnlock: false),
]);
