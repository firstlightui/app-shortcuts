<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts\Console;

use FirstlightUI\AppShortcuts\AppShortcuts;
use FirstlightUI\AppShortcuts\Build\AndroidShortcuts;
use FirstlightUI\AppShortcuts\Build\IosShortcuts;
use FirstlightUI\AppShortcuts\Shortcut;
use Native\Mobile\Plugins\Commands\NativePluginHookCommand;
use Native\Mobile\Support\PlistDocument;
use RuntimeException;

/**
 * The post_compile hook: writes the shortcuts in config/app-shortcuts.php into the native project.
 *
 * NativePHP rebuilds the plugin source tree on every compile, so iOS output goes there fresh;
 * Info.plist and AndroidManifest.xml persist, so their entries are replaced, never appended.
 */
final class CompileShortcutsCommand extends NativePluginHookCommand
{
    protected $signature = 'nativephp:app-shortcuts:compile';

    protected $description = 'Write the app shortcuts from config into the native project.';

    public function handle(AppShortcuts $shortcuts): int
    {
        $configured = $shortcuts->configured();

        if ($this->isIos()) {
            $this->writeIos($configured);
        } elseif ($this->isAndroid()) {
            $this->writeAndroid($configured);
        }

        $this->components->twoColumnDetail('<fg=blue>App shortcuts</>', count($configured).' for '.$this->platform());

        return self::SUCCESS;
    }

    /**
     * @param  list<Shortcut>  $configured
     */
    private function writeIos(array $configured): void
    {
        $directory = $this->buildPath().'/NativePHP/Bridge/Plugins/AppShortcuts';
        $this->ensureDirectory($directory);
        file_put_contents($directory.'/FirstlightShortcutsGenerated.swift', IosShortcuts::swift($configured));

        // The device and simulator targets read separate plists, as NativePHP's own compiler knows.
        foreach (['/NativePHP/Info.plist', '/NativePHP-simulator-Info.plist'] as $relative) {
            $path = $this->buildPath().$relative;

            if (! is_file($path)) {
                continue;
            }

            $plist = PlistDocument::fromXml($this->read($path));
            $existing = $plist->get('UIApplicationShortcutItems');
            $plist->set('UIApplicationShortcutItems', IosShortcuts::quickActions(is_array($existing) ? array_values($existing) : [], $configured));
            file_put_contents($path, $plist->toXml());
        }
    }

    /**
     * @param  list<Shortcut>  $configured
     */
    private function writeAndroid(array $configured): void
    {
        $res = $this->buildPath().'/app/src/main/res';
        $xml = AndroidShortcuts::xml($configured, $this->appId());

        $this->writeOrDelete($res.'/xml/firstlight_shortcuts.xml', $xml);
        $this->writeOrDelete($res.'/values/firstlight_shortcut_strings.xml', AndroidShortcuts::strings($configured));

        $manifestPath = $this->buildPath().'/app/src/main/AndroidManifest.xml';
        file_put_contents($manifestPath, AndroidShortcuts::manifest($this->read($manifestPath), $xml !== null));
    }

    private function writeOrDelete(string $path, ?string $contents): void
    {
        if ($contents === null) {
            $this->delete($path);

            return;
        }

        $this->ensureDirectory(dirname($path));
        file_put_contents($path, $contents);
    }

    private function read(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("app-shortcuts could not read {$path}.");
        }

        return $contents;
    }
}
