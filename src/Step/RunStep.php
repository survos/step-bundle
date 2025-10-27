<?php declare(strict_types=1);

// file: src/Step/RunStep.php
namespace Survos\StepBundle\Step;

use Symfony\Component\Process\Process;
use Castor\Context;
use Survos\StepBundle\Action\AbstractAction;
use Survos\StepBundle\Action\ToCommandConvertible;
use function Castor\io;
use function Castor\run;

final class RunStep
{
    /**
     * @param array<int, mixed> $actions
     * @param Context $ctx
     */
    public static function run(array $actions, Context $ctx): void
    {
        $n = \count($actions);
        if (\function_exists('Castor\io')) {
            io()->writeln(sprintf('DEBUG[runstep]: %d action(s)', $n));
        }

        if ($n === 0) {
            \fwrite(\STDERR, "[runstep] no actions\n");
            return;
        }

        $i = 0;
        foreach ($actions as $action) {
            assert($action instanceof AbstractAction, "Invalid action");
            $i++;

            // 1) If object exposes toCommand(Context): string|array|null
            if (\is_object($action) && \method_exists($action, 'toCommand')) {
                /** @var string|array|null $spec */
                $spec = $action->toCommand($ctx);
                // ack, wrong!
                if ($spec === null) {
                    dd($action::class);
                    // Skipped by a guard (e.g., IfFileMissing)
                    io()->writeln("DEBUG[runstep] #{$i} skipped by guard");
                    continue;
                }
                self::exec($spec, $ctx, $i);
                continue;
            }

            // 2) Plain string or tokenized array
            if (\is_string($action) || (\is_array($action) && self::isVector($action))) {
                self::exec($action, $ctx, $i);
                continue;
            }

            // 3) Associative array spec: ['cmd'=>..., 'cwd'=>..., 'env'=>...]
            if (\is_array($action) && isset($action['cmd'])) {
                $cmd = $action['cmd'];
                $cwd = $action['cwd'] ?? null;
                $env = \is_array($action['env'] ?? null) ? $action['env'] : [];
                $localCtx = ($cwd && \method_exists($ctx, 'withWorkingDirectory')) ? $ctx->withWorkingDirectory($cwd) : $ctx;
                self::exec($cmd, $localCtx, $i, $env);
                continue;
            }

//            dd($action, $action::class);
            \fwrite(\STDERR, "[runstep] skip action #{$i}: unrecognized shape\n");
        }
    }

    /** @param string|array $cmd */
    private static function exec(string|array $cmd, Context $ctx, int $i, array $env = []): void
    {
        if (\function_exists('Castor\io')) {
            io()->writeln('DEBUG[runstep] #'.$i.' '.(\is_string($cmd) ? $cmd : \json_encode($cmd, \JSON_UNESCAPED_SLASHES)));
        }
        // why aren't artifacts created here?
        run($cmd, $ctx, function(string $a, string $b, Process $process): void {
            io()->writeln("$a $b " . $process->getExitCode());
        });
    }

    private static function isVector(array $a): bool
    {
        return array_keys($a) === range(0, count($a) - 1);
    }
}
