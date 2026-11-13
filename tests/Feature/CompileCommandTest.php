<?php

use FirstlightUI\AppShortcuts\Build\IosShortcuts;
use Illuminate\Support\Facades\File;
use Native\Mobile\Support\PlistDocument;

beforeEach(function () {
    $this->build = sys_get_temp_dir().'/app-shortcuts-build-'.bin2hex(random_bytes(4));
    $templates = __DIR__.'/../../vendor/nativephp/mobile/resources';

    File::copy($templates.'/xcode/NativePHP/Info.plist', tap($this->build.'/ios/NativePHP/Info.plist', fn ($p) => File::ensureDirectoryExists(dirname($p))));
    File::copy($templates.'/xcode/NativePHP/Info.plist', $this->build.'/ios/NativePHP-simulator-Info.plist');
    File::copy($templates.'/androidstudio/app/src/main/AndroidManifest.xml', tap($this->build.'/android/app/src/main/AndroidManifest.xml', fn ($p) => File::ensureDirectoryExists(dirname($p))));

    config(['app-shortcuts.shortcuts' => [
        'ward-round' => ['title' => 'Start ward round', 'route' => 'ward-round', 'phrases' => ['Start a ward round in ${applicationName}']],
        'new-task' => ['title' => 'New task', 'route' => 'tasks.create', 'launcher' => false],
    ]]);
});

afterEach(fn () => File::deleteDirectory($this->build));

function compileShortcuts(string $platform, string $path): void
{
    test()->artisan('nativephp:app-shortcuts:compile', [
        '--platform' => $platform,
        '--build-path' => $path,
        '--app-id' => 'com.example.clinic',
    ])->assertSuccessful();
}

it('writes the iOS intents and quick actions, and a rebuild leaves the same files', function () {
    compileShortcuts('ios', $this->build.'/ios');
    compileShortcuts('ios', $this->build.'/ios');

    $swift = File::get($this->build.'/ios/NativePHP/Bridge/Plugins/AppShortcuts/FirstlightShortcutsGenerated.swift');
    expect($swift)->toContain('FirstlightShortcut_new_task: AppIntent');

    foreach (['NativePHP/Info.plist', 'NativePHP-simulator-Info.plist'] as $plist) {
        $items = PlistDocument::fromXml(File::get($this->build.'/ios/'.$plist))->get('UIApplicationShortcutItems');
        expect($items)->toHaveCount(1)
            ->and($items[0]['UIApplicationShortcutItemType'])->toBe(IosShortcuts::QUICK_ACTION_PREFIX.'ward-round');
    }

    // Removing every launcher shortcut removes its quick action on the next build.
    config(['app-shortcuts.shortcuts.ward-round.launcher' => false]);
    compileShortcuts('ios', $this->build.'/ios');
    expect(PlistDocument::fromXml(File::get($this->build.'/ios/NativePHP/Info.plist'))->get('UIApplicationShortcutItems'))->toBe([]);
});

it('writes the Android shortcuts resource and removes it with the last launcher shortcut', function () {
    compileShortcuts('android', $this->build.'/android');

    $xml = $this->build.'/android/app/src/main/res/xml/firstlight_shortcuts.xml';
    expect(File::get($xml))->toContain('com.example.clinic')->not->toContain('new-task')
        ->and(File::get($this->build.'/android/app/src/main/AndroidManifest.xml'))->toContain('@xml/firstlight_shortcuts')
        ->and(File::get($this->build.'/android/app/src/main/res/values/firstlight_shortcut_strings.xml'))->toContain('>Start ward round<');

    config(['app-shortcuts.shortcuts.ward-round.launcher' => false]);
    compileShortcuts('android', $this->build.'/android');

    expect(File::exists($xml))->toBeFalse()
        ->and(File::exists($this->build.'/android/app/src/main/res/values/firstlight_shortcut_strings.xml'))->toBeFalse()
        ->and(File::get($this->build.'/android/app/src/main/AndroidManifest.xml'))->not->toContain('@xml/firstlight_shortcuts');
});
