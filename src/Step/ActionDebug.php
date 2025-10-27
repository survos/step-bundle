<?php declare(strict_types=1);

// file: src/Step/ActionDebug.php
namespace Survos\StepBundle\Step;

use function Castor\context;
use function Castor\io;

final class ActionDebug
{
    /**
     * Wrapper around your project-level _actions_from_current_task().
     * Logs: taskName, guessed castor file, env, action count, first few actions.
     *
     * Falls back to [] if the fetcher isn't defined.
     *
     * @return array<int, mixed>
     */
    public static function actionsFromCurrentTaskDebug(): array
    {
        $ctx = \function_exists('Castor\context') ? context() : null;

        $taskName   = $ctx && \method_exists($ctx, 'getTaskName') ? (string)$ctx->getTaskName() : null;
        $slideshow  = $_ENV['SLIDESHOW']      ?? \getenv('SLIDESHOW')      ?: null;
        $workingDir = $_ENV['WORKING_DIR']    ?? \getenv('WORKING_DIR')    ?: \getcwd();
        $castorFile = self::guessCastorFile($slideshow);

        $hasFetcher = \function_exists('_actions_from_current_task');

        // Light log to stdout (visible under -v / -vv)
        if (\function_exists('Castor\io')) {
            io()->writeln(sprintf(
                'DEBUG[action]: task=%s castor=%s slideshow=%s cwd=%s fetcher=%s',
                $taskName ?? '(unknown)',
                $castorFile ?? '(unknown)',
                $slideshow ?? '(none)',
                $workingDir,
                $hasFetcher ? '_actions_from_current_task' : 'MISSING'
            ));
        } else {
            // fallback to stderr
            \fwrite(\STDERR, "[action] task={$taskName} castor={$castorFile} slideshow={$slideshow} cwd={$workingDir} fetcher=" . ($hasFetcher ? 'present' : 'missing') . "\n");
        }

        if (!$hasFetcher) {
            return [];
        }

        try {
            /** @var array<int,mixed> $actions */
            $actions = _actions_from_current_task();
        } catch (\Throwable $e) {
            \fwrite(\STDERR, "[action] fetcher threw: {$e->getMessage()}\n");
            return [];
        }

        $n = \is_countable($actions) ? \count($actions) : 0;

        if (\function_exists('Castor\io')) {
            io()->writeln(sprintf('DEBUG[action]: fetched %d action(s)', $n));
            foreach (\array_slice((array)$actions, 0, 3, true) as $i => $a) {
                io()->writeln('DEBUG[action] ['.$i.'] ' . (\is_string($a) ? $a : \json_encode($a, \JSON_UNESCAPED_SLASHES)));
            }
        }

        return (array)$actions;
    }

    /**
     * Prefer ${SLIDESHOW}.castor.php if present among included files; otherwise
     * return the last *.castor.php file included. Accept SLIDESHOW_FILE env override.
     */
    public static function guessCastorFile(?string $slideshow): ?string
    {
        $env = $_ENV['SLIDESHOW_FILE'] ?? \getenv('SLIDESHOW_FILE') ?: ($_ENV['CASTOR_FILE'] ?? \getenv('CASTOR_FILE') ?: null);
        if ($env && @\is_file($env)) {
            return $env;
        }

        $included = \get_included_files();
        $candidates = \array_values(\array_filter($included, static fn($f) => \str_ends_with($f, '.castor.php')));

        if (!$candidates) {
            return null;
        }

        if ($slideshow) {
            // look for a filename that contains the slideshow code
            foreach (\array_reverse($candidates) as $f) {
                if (\str_contains(\basename($f), $slideshow)) {
                    return $f;
                }
            }
        }

        // otherwise last included *.castor.php
        return \end($candidates) ?: null;
    }
}
