<?php

declare(strict_types=1);

namespace FirstlightUI\AppShortcuts\Console;

use Carbon\CarbonImmutable;
use FirstlightUI\AppShortcuts\ActionInvocation;
use FirstlightUI\AppShortcuts\AppShortcuts;
use FirstlightUI\AppShortcuts\Shortcut;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Runs one shortcut action as its shortcut fires. The native side calls it through NativePHP's
 * ephemeral or persistent runtime, without opening a screen, and reads the result line.
 *
 * @internal
 */
final class RunActionCommand extends Command
{
    /** Starts the line the native side reads, ahead of anything else the command prints. */
    public const string RESULT_PREFIX = 'app-shortcuts-result:';

    // Options only: NativePHP 4.7's Runtime::artisan passes positional arguments to Artisan by
    // number, so a {shortcut} argument would never arrive.
    protected $signature = 'app-shortcuts:run {--shortcut=} {--id=} {--at=}';

    protected $description = 'Run a shortcut action as its shortcut fires.';

    protected $hidden = true;

    public function handle(AppShortcuts $shortcuts): int
    {
        $shortcut = (string) $this->option('shortcut');
        $id = (string) $this->option('id');
        $at = (string) $this->option('at');

        if (preg_match(Shortcut::ID_PATTERN, $shortcut) !== 1 || preg_match('/^[a-f0-9-]{1,64}$/', $id) !== 1 || preg_match('/^\d{1,15}$/', $at) !== 1) {
            return $this->result(['ran' => false, 'error' => 'invalid invocation']);
        }

        try {
            $reply = $shortcuts->run(new ActionInvocation($id, $shortcut, CarbonImmutable::createFromTimestampMs((int) $at)));
        } catch (Throwable $e) {
            report($e);

            return $this->result(['ran' => false, 'error' => 'the action failed']);
        }

        return $this->result(['ran' => true, 'reply' => $reply]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function result(array $result): int
    {
        // At quiet verbosity, so it is printed even when the command runs quiet: Symfony Console takes
        // its verbosity from the process-wide SHELL_VERBOSITY, which NativePHP's iOS queue worker sets
        // to quiet on another thread while it runs `queue:work --quiet`.
        $this->output->writeln(
            self::RESULT_PREFIX.json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            OutputInterface::VERBOSITY_QUIET,
        );

        return $result['ran'] === true ? self::SUCCESS : self::FAILURE;
    }
}
