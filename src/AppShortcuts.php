<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts;

use Carbon\CarbonImmutable;
use FirstlightUI\AppShortcuts\Events\ActionRan;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * PHP side of the app-shortcuts native plugin.
 *
 * Shortcuts in config/app-shortcuts.php are compiled into the app at build time. push(), remove()
 * and clear() manage the shortcuts an app adds while it runs, such as a recently opened list. They
 * return whether the native side applied the change: false means the bridge function is missing (no
 * native build, or the plugin is not registered) or reported an error. run() and
 * runQueuedActions() run shortcut actions.
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
     * Run one shortcut action's class and return the reply for Siri or Android to show: the string
     * the class returned, or else the shortcut's configured reply. An ActionRan event follows.
     *
     * The plugin calls this as the shortcut fires, through the app-shortcuts:run command, and for
     * each queued invocation. Whatever the class throws reaches the caller.
     */
    public function run(ActionInvocation $invocation): ?string
    {
        $shortcut = $this->action($invocation->shortcutId);

        if ($shortcut === null || $shortcut->action === null) {
            throw new InvalidArgumentException("[{$invocation->shortcutId}] is not an action in config/app-shortcuts.php.");
        }

        $handler = app($shortcut->action);

        if (! is_callable($handler)) {
            throw new InvalidArgumentException("The action of shortcut [{$shortcut->id}] is not invokable.");
        }

        $result = $handler($invocation);
        event(new ActionRan($shortcut->id, $invocation->id));

        $reply = is_string($result) ? trim((string) preg_replace('/\s+/', ' ', $result)) : '';

        return $reply !== '' ? $reply : $shortcut->reply;
    }

    /**
     * Run the shortcut actions the native side has queued, oldest first, and return how many ran.
     *
     * Actions are queued when PHP could not run them as the shortcut fired, such as on iOS when the
     * app was closed and its Laravel bundle had still to be unpacked. The service provider calls
     * this when the first screen of a launch mounts and whenever the native side queues an action
     * while PHP runs, so apps rarely need to. Each invocation leaves the native queue before its
     * class runs: a class that throws is reported and not retried, and one invocation never runs
     * twice.
     */
    public function runQueuedActions(): int
    {
        $queued = $this->request('AppShortcuts.TakeQueued')['invocations'] ?? [];

        if (! is_array($queued) || $queued === []) {
            return 0;
        }

        $ran = 0;

        foreach ($queued as $entry) {
            $id = is_array($entry) ? ($entry['id'] ?? null) : null;
            $shortcutId = is_array($entry) ? ($entry['shortcut'] ?? null) : null;
            $at = is_array($entry) ? ($entry['at'] ?? null) : null;

            if (! is_string($id) || ! is_string($shortcutId) || ! is_int($at)) {
                Log::warning('app-shortcuts dropped a malformed queued action.', ['entry' => $entry]);

                continue;
            }

            // An action removed from config since the build that queued it.
            if ($this->action($shortcutId)?->action === null) {
                Log::warning("app-shortcuts dropped a queued run of [{$shortcutId}], which is no longer an action in config/app-shortcuts.php.");

                continue;
            }

            try {
                $this->run(new ActionInvocation($id, $shortcutId, CarbonImmutable::createFromTimestampMs($at)));
                $ran++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $ran;
    }

    private function action(string $id): ?Shortcut
    {
        foreach ($this->configured() as $shortcut) {
            if ($shortcut->id === $id) {
                return $shortcut;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function call(string $method, array $parameters = []): bool
    {
        return $this->request($method, $parameters) !== null;
    }

    /**
     * The decoded result of a bridge function, or null when it is missing or reported an error.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<mixed>|null
     */
    private function request(string $method, array $parameters = []): ?array
    {
        if (! function_exists('nativephp_call')) {
            return null;
        }

        $response = nativephp_call($method, json_encode((object) $parameters, JSON_THROW_ON_ERROR));
        $decoded = $response === null ? null : json_decode($response, true);

        return is_array($decoded) && ($decoded['status'] ?? null) !== 'error' ? $decoded : null;
    }
}
