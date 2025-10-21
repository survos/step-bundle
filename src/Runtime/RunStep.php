<?php declare(strict_types=1);
/**
 * File: src/Runtime/RunStep.php
 */
namespace Survos\StepBundle\Runtime;

use Survos\StepBundle\Metadata\Step;
use Survos\StepBundle\Metadata\Actions\{
    Bash,
    Console,
    Composer,
    Env,
    OpenUrl,
    YamlWrite,
    FileWrite,
    FileCopy,
    DisplayCode,
    Section,
    BrowserVisit,
    BrowserClick,
    BrowserAssert,
    PhpClosure,
    ShowClass,
    ComposerRequire,
    ImportmapRequire,
    RequirePackage
};

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

use function Castor\io as castor_io;
use function Castor\run as castor_run;
use function Castor\context as castor_context;

/**
 * Executes the #[Step] attached to the *calling task function/method*.
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
            return;
        }

        $io = castor_io();

        $io->title($step->title);
        if ($step->description) {
            $io->note($step->description);
        }
        if ($step->bullets) {
            $io->listing($step->bullets);
        }

        if ($mode === 'present') {
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
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 16);

        foreach ($trace as $frame) {
            if (isset($frame['function']) && is_string($frame['function']) && function_exists($frame['function'])) {
                $rf = new ReflectionFunction($frame['function']);
                $attrs = $rf->getAttributes(Step::class);
                if ($attrs) {
                    /** @var Step $step */
                    return $attrs[0]->newInstance();
                }
            }
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
            $cmd = self::sanitizeShell($a->cmd);
            castor_run(['bash', '-lc', $cmd], context: $ctx);
            return;
        }

        if ($a instanceof Console) {
            $argv = array_merge(self::consolePrefix($a->cwd), self::explodeArgs($a->cmd));
            $ctx  = $a->cwd ? castor_context()->withWorkingDirectory($a->cwd) : null;
            castor_run($argv, context: $ctx);
            return;
        }

        if ($a instanceof Composer) {
            $ctx = $a->cwd ? castor_context()->withWorkingDirectory($a->cwd) : null;
            $cmd = 'composer ' . self::sanitizeShell($a->cmd);
            castor_run(['bash', '-lc', $cmd], context: $ctx);
            return;
        }

        /** ComposerRequire → execute without comments/backslashes */
        if ($a instanceof ComposerRequire) {
            $ctx = $a->cwd ? castor_context()->withWorkingDirectory($a->cwd) : null;

            // Build argv form: composer req [--dev] pkg[:constraint] ...
            $argv = ['composer', 'req'];
            if (property_exists($a, 'dev') && $a->dev) {
                $argv[] = '--dev';
            }
            foreach ((array)$a->requires as $req) {
                if ($req instanceof RequirePackage) {
                    $pkg = $req->package . ($req->constraint ? ':' . $req->constraint : '');
                    if ($pkg) $argv[] = $pkg;
                } elseif (\is_array($req)) {
                    $pkg = (string)($req['package'] ?? ($req[0] ?? ''));
                    $con = (string)($req['constraint'] ?? '');
                    $argv[] = $pkg . ($con ? ':' . $con : '');
                } else {
                    $argv[] = (string)$req;
                }
            }

            castor_run($argv, context: $ctx);
            return;
        }

        /** ImportmapRequire → run via bin/console (or symfony console) with argv */
        if ($a instanceof ImportmapRequire) {
            $argv = array_merge(self::consolePrefix($a->cwd), ['importmap:require']);
            foreach ((array)$a->requires as $req) {
                if ($req instanceof RequirePackage) {
                    $argv[] = $req->package;
                } elseif (\is_array($req)) {
                    $argv[] = (string)($req['package'] ?? ($req[0] ?? ''));
                } else {
                    $argv[] = (string)$req;
                }
            }
            $ctx = $a->cwd ? castor_context()->withWorkingDirectory($a->cwd) : null;
            castor_run($argv, context: $ctx);
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
            $target = $a->target ?? '(inline code)';
            $io->comment('DisplayCode: ' . (is_string($target) ? $target : '(inline)'));
            return;
        }

        if ($a instanceof ShowClass) {
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

    /** Remove shell comments and line continuations for execution */
    private static function sanitizeShell(string $cmd): string
    {
        $lines = preg_split("/\r?\n/", $cmd) ?: [];
        $out = [];
        foreach ($lines as $line) {
            // strip inline comments not in quotes (quick heuristic)
            if (false !== ($pos = strpos($line, '#'))) {
                $before = substr($line, 0, $pos);
                // keep if the # was inside quotes (simple heuristic: if unmatched quotes present, don't strip)
                $q = substr_count($before, "'") % 2 || substr_count($before, '"') % 2;
                $line = $q ? $line : $before;
            }
            $line = rtrim($line);
            $line = preg_replace('/\s*\\\\\s*$/', '', $line); // remove trailing backslash line-continue
            if ($line !== '') $out[] = $line;
        }
        return trim(implode(' ', $out));
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
