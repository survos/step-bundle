<?php declare(strict_types=1);
/**
 * File: src/Runtime/RunStep.php
 */
namespace Survos\StepBundle\Runtime;

use Survos\StepBundle\Metadata\Step;
use Survos\StepBundle\Metadata\Actions\{
    Bash, Console, Composer, Env, OpenUrl, YamlWrite, FileWrite, FileCopy,
    DisplayCode, Section, BrowserVisit, BrowserClick, BrowserAssert, PhpClosure, ShowClass
};

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

use function Castor\io as castor_io;
use function Castor\run as castor_run;
use function Castor\context as castor_context;

/**
 * Executes the #[Step] attached to the *calling task function/method*.
 * - `run_step()` has no name arg; we detect the caller via backtrace.
 * - ShowClass: resolves class via autoloader and prints file in CLI; renderers show code block.
 */
final class RunStep
{
    /**
     * @param array{mode?:'run'|'present'|'dry'} $options
     */
    public static function run(array $options = []): void
    {
        $mode = (string)($options['mode'] ?? getenv('CASTOR_MODE') ?: 'run');

        $step = self::locateCallingStep();
        if (!$step instanceof Step) {
            // No #[Step] on caller — nothing to do.
            return;
        }

        $io = castor_io();

        // Header / description / bullets
        $io->title($step->title);
        if ($step->description) {
            $io->note($step->description);
        }
        if ($step->bullets) {
            $io->listing($step->bullets);
        }

        if ($mode === 'present') {
            // Presenter mode: render only, do not execute
            return;
        }

        foreach ($step->actions as $action) {
            if ($action instanceof Section) {
                $io->section($action->title);
                continue;
            }

            if ($mode === 'dry') {
                $io->comment('DRY: ' . self::summarize($action));
                continue;
            }

            self::executeAction($io, $action);
        }

        $io->success('Completed: ' . $step->title);
    }

    private static function locateCallingStep(): ?Step
    {
        // Scan a handful of frames upward to find a function/method with #[Step]
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);

        foreach ($trace as $frame) {
            // Function-style task
            if (isset($frame['function']) && is_string($frame['function']) && function_exists($frame['function'])) {
                $rf = new ReflectionFunction($frame['function']);
                $attrs = $rf->getAttributes(Step::class);
                if ($attrs) {
                    /** @var Step $step */
                    return $attrs[0]->newInstance();
                }
            }
            // Method-style task
            if (isset($frame['class'], $frame['function']) && method_exists($frame['class'], $frame['function'])) {
                $rm = new ReflectionMethod($frame['class'], $frame['function']);
                $attrs = $rm->getAttributes(Step::class);
                if ($attrs) {
                    /** @var Step $step */
                    return $attrs[0]->newInstance();
                }
            }
        }

        return null;
    }

    private static function summarize(object $a): string
    {
        return (new ReflectionClass($a))->getShortName();
    }

    private static function executeAction(object $io, object $a): void
    {
        if ($a instanceof Bash) {
            $ctx = $a->cwd ? castor_context()->withWorkingDirectory($a->cwd) : null;
            castor_run(['bash', '-lc', $a->cmd], context: $ctx);
            return;
        }

        if ($a instanceof Console) {
            $argv = array_merge(self::consolePrefix($a->cwd), self::explodeArgs($a->cmd));
            $ctx  = $a->cwd ? castor_context()->withWorkingDirectory($a->cwd) : null;
            castor_run($argv, context: $ctx);
            return;
        }

        if ($a instanceof Composer) {
            $cmd = 'composer ' . $a->cmd;
            $ctx = $a->cwd ? castor_context()->withWorkingDirectory($a->cwd) : null;
            castor_run(['bash', '-lc', $cmd], context: $ctx);
            return;
        }

        if ($a instanceof Env) {
            $file = ($a->cwd ? rtrim($a->cwd, '/') . '/' : '') . ($a->file ?? '.env.local');
            self::writeEnvKV($file, $a->key, $a->value);
            return;
        }

        if ($a instanceof OpenUrl) {
            $io->comment('Open: ' . $a->urlOrRoute);
            return;
        }

        if ($a instanceof YamlWrite)  { self::fileWrite(self::joinPath($a->cwd, $a->path), $a->content); return; }
        if ($a instanceof FileWrite)  { self::fileWrite(self::joinPath($a->cwd, $a->path), $a->content); return; }
        if ($a instanceof FileCopy)   {
            $to = self::joinPath($a->cwd, $a->to);
            @mkdir(\dirname($to), 0777, true);
            if (!@copy(self::joinPath($a->cwd, $a->from), $to)) {
                throw new \RuntimeException('Failed to copy to ' . $to);
            }
            return;
        }

        if ($a instanceof DisplayCode) {
            // Renderer handles visual code block; CLI runs no-op here.
            $io->comment('DisplayCode: ' . $a->target);
            return;
        }

        if ($a instanceof ShowClass) {
            // Try to reflect the class; if found, echo the file in CLI;
            // your slideshow renderer can still show it as a code block.
            $class = $a->class;
            if (!\class_exists($class)) {
                $io->comment("⚠ Class not found: {$class} (did you run the previous step?)");
                return;
            }
            $rc = new ReflectionClass($class);
            $file = $rc->getFileName();
            if (!$file || !is_file($file)) {
                $io->comment("⚠ Cannot locate file for {$class}");
                return;
            }
            $io->section($class);
            // Print source to CLI; ensure trailing newline
            $src = (string)\file_get_contents($file);
            echo rtrim($src) . "\n";
            return;
        }

        if ($a instanceof BrowserVisit || $a instanceof BrowserClick || $a instanceof BrowserAssert) {
            $io->comment('Browser step');
            return;
        }

        if ($a instanceof PhpClosure) {
            ($a->fn)();
            return;
        }

        $io->comment('Unknown action: ' . self::summarize($a));
    }

    /** @return array<int,string> */
    private static function consolePrefix(?string $cwd): array
    {
        $bin = $cwd ? rtrim($cwd, '/') . '/bin/console' : 'bin/console';
        return \is_file($bin) ? ['php', $bin] : ['symfony', 'console'];
    }

    private static function writeEnvKV(string $file, string $key, string $value): void
    {
        @mkdir(\dirname($file), 0777, true);
        $lines = \is_file($file)
            ? \preg_split("/\r?\n/", (string)\file_get_contents($file))
            : [];

        $found = false;
        $out   = [];

        foreach ($lines as $line) {
            if (\preg_match('/^\s*' . \preg_quote($key, '/') . '\s*=/', (string)$line)) {
                $out[] = "$key=$value";
                $found = true;
            } else {
                $out[] = (string)$line;
            }
        }
        if (!$found) {
            $out[] = "$key=$value";
        }

        \file_put_contents($file, \rtrim(\implode("\n", $out)) . "\n");
    }

    private static function fileWrite(string $path, string $content): void
    {
        @mkdir(\dirname($path), 0777, true);
        \file_put_contents($path, $content);
    }

    private static function joinPath(?string $cwd, string $path): string
    {
        return $cwd ? rtrim($cwd, '/') . '/' . ltrim($path, '/') : $path;
    }

    /** @return array<int,string> */
    private static function explodeArgs(string $cmd): array
    {
        $out = [];
        $len = \strlen($cmd);
        $buf = '';
        $inS = false; $inD = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $cmd[$i];
            if ($ch === "'" && !$inD) { $inS = !$inS; continue; }
            if ($ch === '"' && !$inS) { $inD = !$inD; continue; }
            if (\ctype_space($ch) && !$inS && !$inD) {
                if ($buf !== '') { $out[] = $buf; $buf = ''; }
                continue;
            }
            $buf .= $ch;
        }
        if ($buf !== '') { $out[] = $buf; }
        return $out;
    }
}
