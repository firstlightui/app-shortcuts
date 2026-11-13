<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts;

use Illuminate\Contracts\Routing\UrlGenerator;
use InvalidArgumentException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

/**
 * One shortcut: a title and either the app route it opens or the action it runs.
 *
 * A route is resolved to a path when the shortcut is made, so the native side only ever receives a
 * path such as `/tasks/create`, which it opens like any other deep link. An action is an invokable
 * class; the native side only queues the shortcut's id, and PHP runs the class when it takes the queue.
 */
final readonly class Shortcut
{
    /** Lowercase words joined by single hyphens. The id names native types, so it must stay stable. */
    public const string ID_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const string APPLICATION_NAME = '${applicationName}';

    /** The SF Symbol an App Shortcut shows when the config names none. */
    public const string DEFAULT_ICON = 'arrow.up.forward.app';

    /**
     * @param  list<string>  $phrases
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $shortTitle,
        public ?string $path,
        public ?string $icon = null,
        public array $phrases = [],
        public bool $launcher = true,
        public bool $requiresUnlock = true,
        public ?string $action = null,
        public ?string $reply = null,
    ) {
        if (preg_match(self::ID_PATTERN, $id) !== 1 || strlen($id) > 64) {
            throw new InvalidArgumentException("The shortcut id [{$id}] must be lowercase letters and digits joined by single hyphens, such as new-task, and at most 64 characters.");
        }

        foreach (array_filter(['title' => $title, 'short title' => $shortTitle, 'reply' => $reply], is_string(...)) as $field => $text) {
            if (trim($text) === '' || preg_match('/[\x00-\x1F\x7F]/', $text) === 1) {
                throw new InvalidArgumentException("The {$field} of shortcut [{$id}] must be one line of text.");
            }
        }

        if (($path === null) === ($action === null)) {
            throw new InvalidArgumentException("Shortcut [{$id}] must either open a route or run an action, not both or neither.");
        }

        if ($path !== null && (! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\s\x00-\x1F\x7F]/', $path) === 1)) {
            throw new InvalidArgumentException("Shortcut [{$id}] must open an app path such as /tasks/create, not [{$path}].");
        }

        if ($action !== null && (! class_exists($action) || ! method_exists($action, '__invoke'))) {
            throw new InvalidArgumentException("The action of shortcut [{$id}] must be an invokable class, and [{$action}] is not.");
        }

        if ($reply !== null && $action === null) {
            throw new InvalidArgumentException("Shortcut [{$id}] opens a route, so it has no reply; only actions answer without opening the app.");
        }

        if ($icon !== null && preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $icon) !== 1) {
            throw new InvalidArgumentException("The icon of shortcut [{$id}] must be an SF Symbol name such as plus.circle, not [{$icon}].");
        }

        foreach ($phrases as $phrase) {
            if (preg_match('/[\x00-\x1F\x7F]/', $phrase) === 1) {
                throw new InvalidArgumentException("Every phrase of shortcut [{$id}] must be one line of text.");
            }

            // Siri only registers a phrase that names the app, and only one placeholder exists.
            if (substr_count($phrase, self::APPLICATION_NAME) !== 1 || substr_count($phrase, '${') !== 1) {
                throw new InvalidArgumentException("The phrase [{$phrase}] of shortcut [{$id}] must contain \${applicationName} exactly once, and no other placeholder.");
            }
        }
    }

    /**
     * A shortcut from its entry in config/app-shortcuts.php.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $id, array $config): self
    {
        $unknown = array_diff(array_keys($config), ['title', 'short_title', 'route', 'parameters', 'action', 'reply', 'icon', 'phrases', 'launcher', 'requires_unlock']);

        if ($unknown !== []) {
            throw new InvalidArgumentException("Shortcut [{$id}] has unknown keys: ".implode(', ', $unknown).'.');
        }

        $title = $config['title'] ?? null;
        $route = $config['route'] ?? null;
        $action = $config['action'] ?? null;

        $opensRoute = is_string($route) && $action === null;
        $runsAction = is_string($action) && $route === null;

        if (! is_string($title) || ! ($opensRoute || $runsAction)) {
            throw new InvalidArgumentException("Shortcut [{$id}] needs a title and either a route name or an action class.");
        }

        if ($action !== null && array_key_exists('parameters', $config)) {
            throw new InvalidArgumentException("Shortcut [{$id}] runs an action, so it takes no route parameters.");
        }

        $phrases = $config['phrases'] ?? [];

        if (! is_array($phrases) || ! array_is_list($phrases) || array_filter($phrases, fn (mixed $p) => ! is_string($p)) !== []) {
            throw new InvalidArgumentException("The phrases of shortcut [{$id}] must be a list of strings.");
        }

        return new self(
            id: $id,
            title: $title,
            shortTitle: self::optionalString($config, 'short_title', $id) ?? $title,
            path: $opensRoute ? self::path($id, $route, $config['parameters'] ?? []) : null,
            icon: self::optionalString($config, 'icon', $id),
            phrases: $phrases,
            launcher: self::bool($config, 'launcher', $id),
            requiresUnlock: self::bool($config, 'requires_unlock', $id),
            action: $runsAction ? $action : null,
            reply: self::optionalString($config, 'reply', $id),
        );
    }

    /**
     * The path of a named route, without the host, such as `/tasks/create?list=3`.
     */
    public static function path(string $id, string $route, mixed $parameters = []): string
    {
        if (! is_array($parameters)) {
            throw new InvalidArgumentException("The parameters of shortcut [{$id}] must be an array.");
        }

        try {
            return app(UrlGenerator::class)->route($route, $parameters, false);
        } catch (RouteNotFoundException) {
            throw new InvalidArgumentException("Shortcut [{$id}] opens the route [{$route}], which does not exist.");
        } catch (Throwable $e) {
            throw new InvalidArgumentException("Shortcut [{$id}] cannot build a path for the route [{$route}]: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function optionalString(array $config, string $key, string $id): ?string
    {
        $value = $config[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException("The {$key} of shortcut [{$id}] must be a string.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function bool(array $config, string $key, string $id): bool
    {
        $value = $config[$key] ?? true;

        if (! is_bool($value)) {
            throw new InvalidArgumentException("The {$key} option of shortcut [{$id}] must be true or false.");
        }

        return $value;
    }
}
