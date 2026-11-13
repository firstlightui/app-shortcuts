<?php

use FirstlightUI\AppShortcuts\Build\AndroidShortcuts;
use FirstlightUI\AppShortcuts\Build\IosShortcuts;
use FirstlightUI\AppShortcuts\Shortcut;
use FirstlightUI\AppShortcuts\Tests\Fixtures\RecordingAction;

function wardRound(): Shortcut
{
    return new Shortcut('ward-round', 'Start ward round', 'Ward round', '/ward-round', 'stethoscope', ['Start a ward round in ${applicationName}']);
}

it('writes an App Intent per shortcut and an App Shortcut only for those with phrases', function () {
    $swift = IosShortcuts::swift([
        wardRound(),
        new Shortcut('new-task', 'New task', 'New task', '/tasks/create', requiresUnlock: false),
    ]);

    expect($swift)
        ->toContain('struct FirstlightShortcut_ward_round: AppIntent')
        ->toContain('struct FirstlightShortcut_new_task: AppIntent')
        ->toContain('AppShortcutsRouter.open(path: "/ward-round")')
        ->toContain('phrases: ["Start a ward round in \(.applicationName)"]')
        ->toContain('systemImageName: "stethoscope"')
        ->and(substr_count($swift, 'AppShortcut('))->toBe(1)
        ->and(substr_count($swift, '.requiresLocalDeviceAuthentication'))->toBe(1);
});

it('escapes titles and paths so they stay Swift literals', function () {
    $swift = IosShortcuts::swift([new Shortcut('q', 'Say "hi" \(x)', 'Q', '/n?q="y"', null, ['"Go" in ${applicationName}'])]);

    expect($swift)
        ->toContain('"Say \"hi\" \\\\(x)"')
        ->toContain('open(path: "/n?q=\"y\"")')
        ->toContain('["\"Go\" in \(.applicationName)"]');
});

it('writes nothing to compile when there are no shortcuts', function () {
    expect(IosShortcuts::swift([]))->not->toContain('import AppIntents');
});

it('replaces only its own quick actions in Info.plist', function () {
    $other = ['UIApplicationShortcutItemType' => 'com.example.other', 'UIApplicationShortcutItemTitle' => 'Other'];
    $stale = ['UIApplicationShortcutItemType' => IosShortcuts::QUICK_ACTION_PREFIX.'old', 'UIApplicationShortcutItemTitle' => 'Old'];

    $items = IosShortcuts::quickActions([$other, $stale], [
        wardRound(),
        new Shortcut('hidden', 'Hidden', 'Hidden', '/hidden', launcher: false),
    ]);

    expect($items)->toBe([
        $other,
        [
            'UIApplicationShortcutItemType' => IosShortcuts::QUICK_ACTION_PREFIX.'ward-round',
            'UIApplicationShortcutItemTitle' => 'Start ward round',
            'UIApplicationShortcutItemIconSymbolName' => 'stethoscope',
            'UIApplicationShortcutItemUserInfo' => ['path' => '/ward-round'],
        ],
    ]);
});

it('writes launcher shortcuts that open the trampoline with a hostless link', function () {
    $xml = AndroidShortcuts::xml([
        wardRound(),
        new Shortcut('at', '@string/x & <y>', '?attr', '/a?b=1&c=2'),
        new Shortcut('hidden', 'Hidden', 'Hidden', '/hidden', launcher: false),
    ], 'com.example.app');

    $doc = simplexml_load_string((string) $xml);
    $android = 'http://schemas.android.com/apk/res/android';

    expect($doc->shortcut)->toHaveCount(2);

    $first = $doc->shortcut[0]->attributes($android);
    $intent = $doc->shortcut[0]->intent->attributes($android);
    expect((string) $first->shortcutId)->toBe('firstlight.ward-round')
        ->and((string) $first->shortcutShortLabel)->toBe('@string/firstlight_shortcut_ward_round_short')
        ->and((string) $first->shortcutLongLabel)->toBe('@string/firstlight_shortcut_ward_round_long')
        ->and((string) $intent->targetPackage)->toBe('com.example.app')
        ->and((string) $intent->targetClass)->toBe(AndroidShortcuts::TRAMPOLINE)
        ->and((string) $intent->data)->toBe('firstlight-shortcut:///ward-round');

    expect((string) $doc->shortcut[1]->intent->attributes($android)->data)->toBe('firstlight-shortcut:///a?b=1&c=2');

    expect(AndroidShortcuts::xml([new Shortcut('h', 'H', 'H', '/h', launcher: false)], 'x'))->toBeNull();
});

it('writes labels as string resources that aapt reads back as the literal text', function () {
    $strings = simplexml_load_string((string) AndroidShortcuts::strings([
        wardRound(),
        new Shortcut('at', '@string/x & <y>', '?attr', '/a'),
        new Shortcut('quotes', "Today's \"list\" \\ more", 'Q', '/q'),
        new Shortcut('hidden', 'Hidden', 'Hidden', '/hidden', launcher: false),
    ]));

    $values = [];

    foreach ($strings->string as $string) {
        $values[(string) $string['name']] = (string) $string;
    }

    expect($values)->toBe([
        'firstlight_shortcut_ward_round_short' => 'Ward round',
        'firstlight_shortcut_ward_round_long' => 'Start ward round',
        'firstlight_shortcut_at_short' => '\\?attr',
        'firstlight_shortcut_at_long' => '\\@string/x & <y>',
        'firstlight_shortcut_quotes_short' => 'Q',
        'firstlight_shortcut_quotes_long' => "Today\\'s \\\"list\\\" \\\\ more",
    ]);

    expect(AndroidShortcuts::strings([new Shortcut('h', 'H', 'H', '/h', launcher: false)]))->toBeNull();
});

it('edits the manifest the same way however often the build runs', function () {
    $template = (string) file_get_contents(__DIR__.'/../../vendor/nativephp/mobile/resources/androidstudio/app/src/main/AndroidManifest.xml');

    $once = AndroidShortcuts::manifest($template, true);
    $twice = AndroidShortcuts::manifest($once, true);

    expect($twice)->toBe($once)
        ->and(substr_count($once, 'android.app.shortcuts'))->toBe(1)
        ->and(substr_count($once, AndroidShortcuts::TRAMPOLINE))->toBe(1)
        ->and(simplexml_load_string($once))->not->toBeFalse();

    // The meta-data sits inside MainActivity, where Android looks for static shortcuts.
    expect($once)->toMatch('/android:name="\.ui\.MainActivity".*?android\.app\.shortcuts.*?<\/activity>/s');

    $without = AndroidShortcuts::manifest($once, false);
    expect($without)->not->toContain('android.app.shortcuts')
        ->and($without)->toContain(AndroidShortcuts::TRAMPOLINE)
        ->and(AndroidShortcuts::manifest($without, false))->toBe($without);
});

function logWater(?string $reply = 'Logged 1 glass & more'): Shortcut
{
    return new Shortcut('log-water', 'Log water', 'Water', null, 'drop', ['Log water in ${applicationName}'], action: RecordingAction::class, reply: $reply);
}

it('writes action intents that queue their id and answer without opening the app', function () {
    $swift = IosShortcuts::swift([logWater(), logWater(null), wardRound()]);

    [$withReply, $silent, $route] = array_slice(explode('struct FirstlightShortcut_', $swift), 1);

    expect($withReply)
        ->toContain('static let openAppWhenRun: Bool = false')
        ->toContain('.requiresLocalDeviceAuthentication')
        ->toContain('some IntentResult & ProvidesDialog')
        ->toContain('try AppShortcutsActions.enqueue(shortcut: "log-water")')
        ->toContain('return .result(dialog: "Logged 1 glass & more")')
        ->not->toContain('AppShortcutsRouter.open');

    expect($silent)
        ->toContain('openAppWhenRun: Bool = false')
        ->not->toContain('ProvidesDialog')
        ->toContain('return .result()');

    expect($route)->toContain('openAppWhenRun: Bool = true');
});

it('gives action quick actions and launcher shortcuts the action id, not a path', function () {
    expect(IosShortcuts::quickActions([], [logWater()])[0]['UIApplicationShortcutItemUserInfo'])->toBe(['action' => 'log-water']);

    $doc = simplexml_load_string((string) AndroidShortcuts::xml([logWater(), logWater(null)], 'com.example.app'));
    $android = 'http://schemas.android.com/apk/res/android';

    expect((string) $doc->shortcut[0]->intent->attributes($android)->data)->toBe('firstlight-shortcut://action/log-water?reply=Logged%201%20glass%20%26%20more')
        ->and((string) $doc->shortcut[1]->intent->attributes($android)->data)->toBe('firstlight-shortcut://action/log-water');
});
