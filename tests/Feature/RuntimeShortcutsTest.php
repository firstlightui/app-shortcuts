<?php

use FirstlightUI\AppShortcuts\Facades\AppShortcuts;
use Native\Mobile\Testing\Native;

it('pushes a shortcut with the path of its route', function () {
    $bridge = Native::fakeBridge()->respondTo('AppShortcuts.Push', []);

    expect(AppShortcuts::push('recent-patient', 'Recent patient', 'patients.show', ['patient' => 42], 'person'))->toBeTrue();

    expect($bridge->callsTo('AppShortcuts.Push')[0]['params'])->toBe([
        'id' => 'recent-patient',
        'title' => 'Recent patient',
        'shortTitle' => 'Recent patient',
        'path' => '/patients/42',
        'icon' => 'person',
    ]);
});

it('reports a native error as false', function () {
    Native::fakeBridge()->respondTo('AppShortcuts.Push', ['status' => 'error', 'code' => 'RATE_LIMITED', 'message' => 'x']);

    expect(AppShortcuts::push('triage', 'Triage', 'ward-round'))->toBeFalse();
});

it('refuses to push over a shortcut built into the app', function () {
    config(['app-shortcuts.shortcuts' => ['ward-round' => ['title' => 'Ward round', 'route' => 'ward-round']]]);
    $bridge = Native::fakeBridge()->respondTo('AppShortcuts.Push', []);

    expect(fn () => AppShortcuts::push('ward-round', 'Ward round', 'ward-round'))
        ->toThrow(InvalidArgumentException::class, 'already used');

    $bridge->assertNotCalled('AppShortcuts.Push');
});

it('removes and clears pushed shortcuts', function () {
    $bridge = Native::fakeBridge()->respondTo('AppShortcuts.Remove', [])->respondTo('AppShortcuts.Clear', []);

    expect(AppShortcuts::remove('triage'))->toBeTrue()
        ->and(AppShortcuts::clear())->toBeTrue();

    expect($bridge->callsTo('AppShortcuts.Remove')[0]['params'])->toBe(['id' => 'triage']);
    $bridge->assertCalledTimes('AppShortcuts.Clear', 1);
});
