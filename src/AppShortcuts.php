<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts;

use InvalidArgumentException;

/**
 * PHP side of the app-shortcuts native plugin.
 *
 * Shortcuts in config/app-shortcuts.php are compiled into the app at build time. The methods here
 * manage the shortcuts an app adds while it runs, such as a recently opened list. They return
 * whether the native side applied the change: false means the bridge function is missing (no
 * native build, or the plugin is not registered) or reported an error.
 */
final class AppShortcuts
{
    /** Apple's limit on App Shortcuts, the only shortcuts Siri and Spotlight offer without setup. */
    public const int MAX_PHRASE_SHORTCUTS = 10;

    /** iOS shows four quick actions, and launchers about as many, so more would vanish on one platform. */
    public const int MAX_LAUNCHER_SHORTCUTS = 4;

    /**
     * The shortcuts built into the app, validated against each other.
     *
     * @return list<Shortcut>
     */
    public function configured(): array
    {
        $entries = config('app-shortcuts.shortcuts', []);

        if (! is_array($entries) || ($entries !== [] && array_is_list($entries))) {
            throw new InvalidArgumentException('app-shortcuts.shortcuts must map each shortcut id to its options.');
        }

        $shortcuts = [];

        foreach ($entries as $id => $options) {
            if (! is_array($options)) {
                throw new InvalidArgumentException("The options of shortcut [{$id}] must be an array.");
            }

            $shortcuts[] = Shortcut::fromConfig((string) $id, $options);
        }

        $withPhrases = count(array_filter($shortcuts, fn (Shortcut $s) => $s->phrases !== []));

        if ($withPhrases > self::MAX_PHRASE_SHORTCUTS) {
            throw new InvalidArgumentException("{$withPhrases} shortcuts have Siri phrases, but iOS allows ".self::MAX_PHRASE_SHORTCUTS.'. Remove the phrases from the rest; they stay in the Shortcuts app.');
        }

        $onLauncher = count(array_filter($shortcuts, fn (Shortcut $s) => $s->launcher));

        if ($onLauncher > self::MAX_LAUNCHER_SHORTCUTS) {
            throw new InvalidArgumentException("{$onLauncher} shortcuts are on the launcher, but only ".self::MAX_LAUNCHER_SHORTCUTS.' fit on both platforms. Set launcher to false on the rest.');
        }

        return $shortcuts;
    }

    /**
     * Add or replace a shortcut in the app icon's long-press menu: a Home Screen quick action on
     * iOS and a dynamic launcher shortcut on Android. The newest comes first. Its title shows
     * without unlocking the app, so keep it free of personal or patient details.
     *
     * @param  array<string, mixed>  $parameters  Route parameters.
     */
    public function push(string $id, string $title, string $route, array $parameters = [], ?string $icon = null, ?string $shortTitle = null): bool
    {
        $shortcut = new Shortcut(
            id: $id,
            title: $title,
            shortTitle: $shortTitle ?? $title,
            path: Shortcut::path($id, $route, $parameters),
            icon: $icon,
        );

        foreach ($this->configured() as $configured) {
            if ($configured->id === $id) {
                throw new InvalidArgumentException("The shortcut id [{$id}] is already used in config/app-shortcuts.php.");
            }
        }

        return $this->call('AppShortcuts.Push', [
            'id' => $shortcut->id,
            'title' => $shortcut->title,
            'shortTitle' => $shortcut->shortTitle,
            'path' => $shortcut->path,
            'icon' => $shortcut->icon,
        ]);
    }

    /**
     * Remove a shortcut added with push(). Removing one that is not there succeeds.
     */
    public function remove(string $id): bool
    {
        if (preg_match(Shortcut::ID_PATTERN, $id) !== 1) {
            throw new InvalidArgumentException("[{$id}] is not a shortcut id.");
        }

        return $this->call('AppShortcuts.Remove', ['id' => $id]);
    }

    /**
     * Remove every shortcut added with push(), such as when someone signs out.
     * Shortcuts from config/app-shortcuts.php stay.
     */
    public function clear(): bool
    {
        return $this->call('AppShortcuts.Clear');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function call(string $method, array $parameters = []): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $response = nativephp_call($method, json_encode((object) $parameters, JSON_THROW_ON_ERROR));

        if ($response === null) {
            return false;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) && ($decoded['status'] ?? null) !== 'error';
    }
}
