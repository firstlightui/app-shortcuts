<?php

use FirstlightUI\AppShortcuts\Events\ActionRan;
use FirstlightUI\AppShortcuts\Events\ActionsQueued;
use FirstlightUI\AppShortcuts\Facades\AppShortcuts;
use FirstlightUI\AppShortcuts\Tests\Fixtures\RecordingAction;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Event;
use Native\Mobile\Events\Screen\ScreenMounted;
use Native\Mobile\Testing\FakeBridge;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    RecordingAction::$ran = [];
    RecordingAction::$failFor = null;

    config(['app-shortcuts.shortcuts' => [
        'log-water' => ['title' => 'Log water', 'action' => RecordingAction::class, 'reply' => 'Logged'],
        'start-timer' => ['title' => 'Start timer', 'action' => RecordingAction::class],
        'ward-round' => ['title' => 'Ward round', 'route' => 'ward-round'],
    ]]);
});

/**
 * A fake native queue that hands over its entries once, as the platforms' TakeQueued does.
 *
 * @param  list<mixed>  $entries
 */
function queuedActions(array $entries): FakeBridge
{
    return Native::fakeBridge()->respondTo('AppShortcuts.TakeQueued', function () use (&$entries) {
        $taken = $entries;
        $entries = [];

        return ['invocations' => $taken];
    });
}

it('runs queued actions oldest first with when and how they were invoked', function () {
    queuedActions([
        ['id' => 'a1', 'shortcut' => 'start-timer', 'at' => 1_794_600_000_123],
        ['id' => 'a2', 'shortcut' => 'log-water', 'at' => 1_794_600_060_000],
    ]);

    expect(AppShortcuts::runQueuedActions())->toBe(2);

    expect(array_map(fn ($i) => [$i->id, $i->shortcutId, $i->invokedAt->getTimestampMs()], RecordingAction::$ran))->toBe([
        ['a1', 'start-timer', 1_794_600_000_123],
        ['a2', 'log-water', 1_794_600_060_000],
    ]);
});

it('runs each invocation once however often the queue is taken', function () {
    queuedActions([['id' => 'a1', 'shortcut' => 'log-water', 'at' => 1]]);

    AppShortcuts::runQueuedActions();
    expect(AppShortcuts::runQueuedActions())->toBe(0)
        ->and(RecordingAction::$ran)->toHaveCount(1);
});

it('reports a failing action, does not retry it and still runs the rest', function () {
    Event::fake([ActionRan::class]);
    RecordingAction::$failFor = 'start-timer';
    $reported = [];
    $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported) {
        $reported[] = $e->getMessage();
    });

    queuedActions([
        ['id' => 'a1', 'shortcut' => 'start-timer', 'at' => 1],
        ['id' => 'a2', 'shortcut' => 'log-water', 'at' => 2],
    ]);

    expect(AppShortcuts::runQueuedActions())->toBe(1)
        ->and($reported)->toBe(['Action start-timer failed'])
        ->and(AppShortcuts::runQueuedActions())->toBe(0);

    Event::assertDispatchedTimes(ActionRan::class, 1);
    Event::assertDispatched(ActionRan::class, fn (ActionRan $e) => $e->shortcutId === 'log-water' && $e->invocationId === 'a2');
});

it('drops queued runs of shortcuts that are no longer actions, and malformed entries', function () {
    queuedActions([
        ['id' => 'a1', 'shortcut' => 'ward-round', 'at' => 1],
        ['id' => 'a2', 'shortcut' => 'removed', 'at' => 2],
        ['shortcut' => 'log-water'],
        'junk',
        ['id' => 'a3', 'shortcut' => 'log-water', 'at' => 3],
    ]);

    expect(AppShortcuts::runQueuedActions())->toBe(1)
        ->and(RecordingAction::$ran[0]->id)->toBe('a3');
});

it('runs nothing without a native build', function () {
    Native::fakeBridge()->withoutCapability('AppShortcuts.TakeQueued');

    expect(AppShortcuts::runQueuedActions())->toBe(0);
});

it('takes the queue when the first screen of a launch mounts and whenever actions are queued', function () {
    $bridge = queuedActions([['id' => 'a1', 'shortcut' => 'log-water', 'at' => 1]]);

    event(new ScreenMounted('App\\Home'));
    event(new ScreenMounted('App\\Settings'));
    $bridge->assertCalledTimes('AppShortcuts.TakeQueued', 1);
    expect(RecordingAction::$ran)->toHaveCount(1);

    event(new ActionsQueued);
    $bridge->assertCalledTimes('AppShortcuts.TakeQueued', 2);
});
