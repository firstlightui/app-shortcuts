<?php

use FirstlightUI\AppShortcuts\Facades\AppShortcuts;
use FirstlightUI\AppShortcuts\Tests\Fixtures\RecordingAction;

function shortcutsConfig(array $shortcuts): void
{
    config(['app-shortcuts.shortcuts' => $shortcuts]);
}

it('resolves each shortcut to the path of its named route', function () {
    shortcutsConfig([
        'ward-round' => ['title' => 'Start ward round', 'route' => 'ward-round', 'phrases' => ['Start a ward round in ${applicationName}']],
        'patient' => ['title' => 'Patient', 'route' => 'patients.show', 'parameters' => ['patient' => 7, 'tab' => 'notes'], 'launcher' => false],
    ]);

    $shortcuts = AppShortcuts::configured();

    expect($shortcuts)->toHaveCount(2)
        ->and($shortcuts[0]->path)->toBe('/ward-round')
        ->and($shortcuts[0]->shortTitle)->toBe('Start ward round')
        ->and($shortcuts[0]->requiresUnlock)->toBeTrue()
        ->and($shortcuts[0]->launcher)->toBeTrue()
        ->and($shortcuts[1]->path)->toBe('/patients/7?tab=notes')
        ->and($shortcuts[1]->launcher)->toBeFalse();
});

it('makes an action shortcut that runs a class instead of opening a route', function () {
    shortcutsConfig(['log-water' => ['title' => 'Log water', 'action' => RecordingAction::class, 'reply' => 'Logged a glass']]);

    $shortcut = AppShortcuts::configured()[0];

    expect($shortcut->path)->toBeNull()
        ->and($shortcut->action)->toBe(RecordingAction::class)
        ->and($shortcut->reply)->toBe('Logged a glass');
});

it('rejects shortcut config the platforms would refuse or silently drop', function (array $shortcuts, string $message) {
    shortcutsConfig($shortcuts);

    expect(fn () => AppShortcuts::configured())->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing route' => [['x' => ['title' => 'X', 'route' => 'nope']], 'does not exist'],
    'missing route parameter' => [['x' => ['title' => 'X', 'route' => 'patients.show']], 'cannot build a path'],
    'phrase without the app name' => [['x' => ['title' => 'X', 'route' => 'ward-round', 'phrases' => ['Start a ward round']]], 'exactly once'],
    'phrase naming the app twice' => [['x' => ['title' => 'X', 'route' => 'ward-round', 'phrases' => ['${applicationName} ${applicationName}']]], 'exactly once'],
    'phrase with another placeholder' => [['x' => ['title' => 'X', 'route' => 'ward-round', 'phrases' => ['${applicationName} ${patient}']]], 'no other placeholder'],
    'id Swift cannot name' => [['New Task' => ['title' => 'X', 'route' => 'ward-round']], 'lowercase letters'],
    'two-line title' => [['x' => ['title' => "X\nY", 'route' => 'ward-round']], 'one line'],
    'icon that is not a symbol name' => [['x' => ['title' => 'X', 'route' => 'ward-round', 'icon' => 'plus circle']], 'SF Symbol'],
    'misspelt option' => [['x' => ['title' => 'X', 'route' => 'ward-round', 'phrase' => ['${applicationName}']]], 'unknown keys: phrase'],
    'list instead of map' => [[['title' => 'X', 'route' => 'ward-round']], 'map each shortcut id'],
    'route and action' => [['x' => ['title' => 'X', 'route' => 'ward-round', 'action' => RecordingAction::class]], 'either a route name or an action class'],
    'neither route nor action' => [['x' => ['title' => 'X']], 'either a route name or an action class'],
    'action that is not a class' => [['x' => ['title' => 'X', 'action' => 'App\\Actions\\Missing']], 'must be an invokable class'],
    'action class that is not invokable' => [['x' => ['title' => 'X', 'action' => stdClass::class]], 'must be an invokable class'],
    'reply on a route' => [['x' => ['title' => 'X', 'route' => 'ward-round', 'reply' => 'Done']], 'only actions answer'],
    'route parameters on an action' => [['x' => ['title' => 'X', 'action' => RecordingAction::class, 'parameters' => ['a' => 1]]], 'takes no route parameters'],
    'two-line reply' => [['x' => ['title' => 'X', 'action' => RecordingAction::class, 'reply' => "A\nB"]], 'reply of shortcut [x] must be one line'],
]);

it('keeps within Apple\'s ten spoken shortcuts and four quick actions', function () {
    $spoken = [];

    foreach (range(1, 11) as $n) {
        $spoken["s{$n}"] = ['title' => "S{$n}", 'route' => 'ward-round', 'phrases' => ["S{$n} in \${applicationName}"], 'launcher' => false];
    }

    shortcutsConfig($spoken);
    expect(fn () => AppShortcuts::configured())->toThrow(InvalidArgumentException::class, '11 shortcuts have Siri phrases');

    unset($spoken['s11']);
    shortcutsConfig($spoken);
    expect(AppShortcuts::configured())->toHaveCount(10);

    $launcher = [];

    foreach (range(1, 5) as $n) {
        $launcher["l{$n}"] = ['title' => "L{$n}", 'route' => 'ward-round'];
    }

    shortcutsConfig($launcher);
    expect(fn () => AppShortcuts::configured())->toThrow(InvalidArgumentException::class, '5 shortcuts are on the launcher');
});
