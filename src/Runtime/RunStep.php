<?php declare(strict_types=1);
/**
 * File: src/Runtime/RunStep.php
 */
namespace Survos\StepBundle\Runtime;

use Castor\Console\Command\TaskCommand;
use Castor\Context as CastorContext;
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
    RequirePackage,
    Artifact as ArtifactSpec
};
use Survos\StepBundle\Util\ArtifactHelper;
use Survos\StepBundle\Util\PathUtil;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Symfony\Component\Process\Process;

use function Castor\io as castor_io;
use function Castor\context as castor_context;

final class RunStep
{
    /**
     * @param array{mode?:'run'|'present'|'dry'} $options
     */
    public static function run(?TaskCommand $task=null, ?CastorContext $ctx=null, array $options = []): void
    {
        $mode = (string)($options['mode'] ?? getenv('CASTOR_MODE') ?: 'run');

        $step = self::locateCallingStep();
        if (!$step instanceof Step) {
            return;
        }

        $io = castor_io();
        $io->title(($task?->getName() ?? '(task)') . ' / ' . ($task?->getDescription() ?? ''));
        $io->title($step->title);
        if ($step->description) $io->note($step->description);
        if ($step->bullets)     $io->listing($step->bullets);

        if ($mode === 'present') {
            return;
        }

        // Artifact helper bound to current step
        $ctx ??= castor_context();
        $ah = ArtifactHelper::fromTaskContext($task, $ctx)->withStep($step->title);

        $actionIndex = 0;
        foreach ($step->actions as $action) {
            $actionIndex++;
            if ($action instanceof Section) {
                $io->section($action->title);
                continue;
            }
            if ($mode === 'dry') {
                $io->comment('DRY: ' . self::summarize($action));
                continue;
            }
            self::executeAction($io, $action, $ctx, $ah, $actionIndex);
        }

        $io->success('Completed: ' . $step->title);
    }

    private static function locateCallingStep(): ?Step
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        foreach ($trace as $frame) {
            if (isset($frame['function']) && is_string($frame['function']) && function_exists($frame['function'])) {
                $rf = new ReflectionFunction($frame['function']);
                $attrs = $rf->getAttributes(Step::class);
                if ($attrs) {
                    return $attrs[0]->newInstance();
                }
            }
            if (isset($frame['class'], $frame['function']) && method_exists($frame['class'], $frame['function'])) {
                $rm = new ReflectionMethod($frame['class'], $frame['function']);
                $attrs = $rm->getAttributes(Step::class);
                if ($attrs) {
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

    // inside RunStep class (private helpers)
    private static function proxyUrl(string $urlOrPath, string $cwdAbs, ?string $hostHint = null): string
    {
        // 1) explicit host wins
        if ($hostHint) {
            $h = preg_match('~^https?://~', $hostHint) ? $hostHint : ('http://' . $hostHint);
            if ($urlOrPath === '/' || str_starts_with($urlOrPath, '/')) {
                return rtrim($h, '/') . $urlOrPath;
            }
            return $h; // already a full URL
        }

        // 2) env hints (SYMFONY_DEFAULT_ROUTE_URL or SURVOS_PROXY_HOST)
        $envUrl = getenv('SURVOS_PROXY_HOST') ?: getenv('SYMFONY_DEFAULT_ROUTE_URL'); // e.g. http://barcode.wip
        if ($envUrl) {
            $envUrl = rtrim($envUrl, '/');
            return ($urlOrPath === '/' || str_starts_with($urlOrPath, '/')) ? $envUrl . $urlOrPath : $envUrl;
        }

        // 3) last resort: just pass through (caller gave a full URL, or local)
        return $urlOrPath;
    }


    private static function executeAction(object $io, object $a, CastorContext $ctx, ArtifactHelper $ah, int $index): void
    {
        $cwdAbs = $a->cwd
            ? PathUtil::absPath($a->cwd, (string)$ctx->workingDirectory)
            : (string)$ctx->workingDirectory;

        $actionKey = self::actionKey($a, $index);

        // collect pre-change git state
        $pre = self::gitStatus($cwdAbs);

        if ($a instanceof Bash) {
            $result = self::runProcess(['bash','-lc', self::sanitizeShell($a->cmd)], $cwdAbs);
            self::writeCommandArtifacts($ah, $actionKey, $result, $cwdAbs, $a);
            self::writeGitArtifacts($ah, $actionKey, $cwdAbs, $pre);
            if ($io->isVerbose()) {
                $io->writeln(sprintf('<comment>Saved:</comment> %s', $ah->publishPath($ah->baseDir() . "/logs/{$actionKey}/command.log")));
            }

            return;
        }

        if ($a instanceof Console) {
            $argv = array_merge(self::consolePrefix($cwdAbs), self::explodeArgs($a->cmd));
            $result = self::runProcess($argv, $cwdAbs);
            self::writeCommandArtifacts($ah, $actionKey, $result, $cwdAbs, $a);
            self::writeGitArtifacts($ah, $actionKey, $cwdAbs, $pre);
            if ($io->isVerbose()) {
                $io->writeln(sprintf('<comment>Saved:</comment> %s', $ah->publishPath($ah->baseDir() . "/logs/{$actionKey}/command.log")));
            }

            // declared artifacts (e.g. snapshot AppController.php)
            foreach ((array)$a->artifacts as $spec) {
                if (!$spec instanceof ArtifactSpec) continue;
                $abs = PathUtil::absPath($spec->sourcePath, $cwdAbs);
                if (is_file($abs)) {
                    $rel = "files/{$actionKey}/{$spec->asName}";
                    $ah->save($rel, (string)file_get_contents($abs));
                }
            }
            return;
        }

        if ($a instanceof Composer) {
            $result = self::runProcess(['bash','-lc', 'composer ' . self::sanitizeShell($a->cmd)], $cwdAbs);
            self::writeCommandArtifacts($ah, $actionKey, $result, $cwdAbs, $a);
            self::writeGitArtifacts($ah, $actionKey, $cwdAbs, $pre);
            return;
        }

        if ($a instanceof ComposerRequire) {
            $argv = ['composer', 'req'];
            if (property_exists($a, 'dev') && $a->dev) $argv[] = '--dev';
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
            $result = self::runProcess($argv, $cwdAbs);
            self::writeCommandArtifacts($ah, $actionKey, $result, $cwdAbs, $a);
            self::writeGitArtifacts($ah, $actionKey, $cwdAbs, $pre);

            return;
        }

        if ($a instanceof ImportmapRequire) {
            $argv = array_merge(self::consolePrefix($cwdAbs), ['importmap:require']);
            foreach ((array)$a->requires as $req) {
                if ($req instanceof RequirePackage) {
                    $argv[] = $req->package;
                } elseif (\is_array($req)) {
                    $argv[] = (string)($req['package'] ?? ($req[0] ?? ''));
                } else {
                    $argv[] = (string)$req;
                }
            }
            $result = self::runProcess($argv, $cwdAbs);
            self::writeCommandArtifacts($ah, $actionKey, $result, $cwdAbs, $a);
            self::writeGitArtifacts($ah, $actionKey, $cwdAbs, $pre);
            return;
        }

        if ($a instanceof Env) {
            $file = ($a->cwd ? rtrim($a->cwd, '/') . '/' : '') . ($a->file ?? '.env.local');
            $abs  = PathUtil::absPath($file, $cwdAbs);
            self::writeEnvKV($abs, $a->key, $a->value);
            // snapshot updated file
            $ah->save("files/{$actionKey}/" . basename($abs), (string)file_get_contents($abs));
            self::writeGitArtifacts($ah, $actionKey, $cwdAbs, $pre);
            return;
        }

        if ($a instanceof YamlWrite)  {
            $abs = PathUtil::absPath(self::joinPath($a->cwd, $a->path), $cwdAbs);
            self::fileWrite($abs, $a->content);
            $ah->save("files/{$actionKey}/" . basename($abs), (string)file_get_contents($abs));
            self::writeGitArtifacts($ah, $actionKey, $cwdAbs, $pre);
            return;
        }

        if ($a instanceof FileWrite)  {
            $abs = PathUtil::absPath(self::joinPath($a->cwd, $a->path), $cwdAbs);
            self::fileWrite($abs, $a->content);
            $ah->save("files/{$actionKey}/" . basename($abs), (string)file_get_contents($abs));
            self::writeGitArtifacts($ah, $actionKey, $cwdAbs, $pre);
            return;
        }

        if ($a instanceof FileCopy)   {
            $toAbs = PathUtil::absPath(self::joinPath($a->cwd, $a->to), $cwdAbs);
            $fromAbs = PathUtil::absPath(self::joinPath($a->cwd, $a->from), $cwdAbs);
            self::ensureDir(\dirname($toAbs));
            if (!copy($fromAbs, $toAbs)) {
                throw new \RuntimeException('Failed to copy to ' . $toAbs);
            }
            $ah->save("files/{$actionKey}/" . basename($toAbs), (string)file_get_contents($toAbs));
            self::writeGitArtifacts($ah, $actionKey, $cwdAbs, $pre);
            return;
        }

        if ($a instanceof DisplayCode) {
            // purely presentational; still leave a breadcrumb
            $ah->save("logs/{$actionKey}/display.txt", "DisplayCode: " . (string)($a->target ?? '(inline)') . "\n");
            return;
        }

        if ($a instanceof ShowClass) {
            $class = $a->class;
            if (!\class_exists($class)) {
                $ah->save("logs/{$actionKey}/showclass.txt", "Class not found: {$class}\n");
                return;
            }
            $rc = new ReflectionClass($class);
            $file = $rc->getFileName();
            if ($file && is_file($file)) {
                $ah->save("files/{$actionKey}/" . basename($file), (string)\file_get_contents($file));
            }
            return;
        }

        if ($a instanceof BrowserVisit) {
            // Build final URL
            $url = $a->urlOrPath;
            if ($a->useProxy) {
                $url = self::proxyUrl($url, $cwdAbs, $a->host);
            }

            // Verbose breadcrumb + artifact log
            if (method_exists($io, 'isVerbose') && $io->isVerbose()) {
                $io->writeln(sprintf('<info>Visit:</info> %s', $url));
            }
            $ah->save("logs/{$actionKey}/browser.txt", "Visit: {$url}\n");

            // If you later automate a real headless fetch/screenshot, do it here.
            return;
        }

        if ($a instanceof PhpClosure) {
            ($a->fn)();
            $ah->save("logs/{$actionKey}/closure.txt", "Executed closure.\n");
            return;
        }

        $ah->save("logs/{$actionKey}/unknown.txt", 'Unknown action: ' . self::summarize($a) . "\n");
    }

    private static function runProcess(array $argv, string $cwd): array
    {
        $p = new Process($argv, $cwd);
        $p->setTimeout(null);
        $p->run();

        return [
            'command' => implode(' ', array_map(fn($s)=> (string)$s, $argv)),
            'exit'    => $p->getExitCode(),
            'stdout'  => $p->getOutput(),
            'stderr'  => $p->getErrorOutput(),
        ];
    }

    private static function writeCommandArtifacts(ArtifactHelper $ah, string $actionKey, array $result, string $cwd, object $a): void
    {
        $log = "# CMD\n{$result['command']}\n\n# EXIT\n{$result['exit']}\n\n# STDOUT\n{$result['stdout']}\n\n# STDERR\n{$result['stderr']}\n";
        $ah->save("logs/{$actionKey}/command.log", $log);
    }

    private static function writeGitArtifacts(ArtifactHelper $ah, string $actionKey, string $cwd, array $pre): void
    {
        if (!is_dir($cwd . '/.git')) return;

        $post = self::gitStatus($cwd);
        $changed = array_values(array_diff($post['names'], $pre['names']));
        if (!$changed) return;

        $diff = self::proc(['git','diff'], $cwd);
        $ah->save("git/{$actionKey}/changes.diff", $diff);

        foreach ($changed as $rel) {
            $abs = $cwd . '/' . $rel;
            if (is_file($abs)) {
                $ah->save("git/{$actionKey}/changed/" . basename($rel), (string)file_get_contents($abs));
            }
        }
    }

    private static function gitStatus(string $cwd): array
    {
        if (!is_dir($cwd . '/.git')) return ['names' => []];
        $out = self::proc(['git','status','--porcelain'], $cwd);
        $names = [];
        foreach (preg_split("/\r?\n/", trim($out)) as $line) {
            if ($line === '') continue;
            $names[] = ltrim(substr($line, 3));
        }
        return ['names' => $names];
    }

    private static function proc(array $argv, string $cwd): string
    {
        $p = new Process($argv, $cwd);
        $p->setTimeout(null);
        $p->run();
        return $p->getOutput() . $p->getErrorOutput();
    }

    /** @return array<int,string> */
    private static function consolePrefix(string $cwdAbs): array
    {
        $bin = $cwdAbs . '/bin/console';
        return \is_file($bin) ? ['php', $bin] : ['symfony', 'console'];
    }

    private static function writeEnvKV(string $absFile, string $key, string $value): void
    {
        self::ensureDir(\dirname($absFile));
        $lines = \is_file($absFile) ? \preg_split("/\r?\n/", (string)\file_get_contents($absFile)) : [];
        $found = false; $out = [];
        foreach ($lines as $line) {
            if (\preg_match('/^\s*' . \preg_quote($key, '/') . '\s*=/', (string)$line)) {
                $out[] = "$key=$value"; $found = true;
            } else {
                $out[] = (string)$line;
            }
        }
        if (!$found) $out[] = "$key=$value";
        \file_put_contents($absFile, \rtrim(\implode("\n", $out)) . "\n");
    }

    private static function fileWrite(string $abs, string $content): void
    {
        self::ensureDir(\dirname($abs));
        \file_put_contents($abs, $content);
    }

    private static function ensureDir(string $dir): void
    {
        if (is_dir($dir)) return;
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: $dir");
        }
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
            if (false !== ($pos = strpos($line, '#'))) {
                $before = substr($line, 0, $pos);
                $q = substr_count($before, "'") % 2 || substr_count($before, '"') % 2;
                $line = $q ? $line : $before;
            }
            $line = rtrim($line);
            $line = preg_replace('/\s*\\\\\s*$/', '', $line);
            if ($line !== '') $out[] = $line;
        }
        return trim(implode(' ', $out));
    }

    /** @return array<int,string> */
    private static function explodeArgs(string $cmd): array
    {
        $out = []; $len = \strlen($cmd); $buf = ''; $inS = false; $inD = false;
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

    private static function actionKey(object $a, int $index): string
    {
        $id = property_exists($a, 'id') ? (string)($a->id ?? '') : '';
        $base = strtolower((new ReflectionClass($a))->getShortName());
        return $id ? ($base . '-' . ArtifactHelper::safe($id)) : ($base . '-' . sprintf('%02d', $index));
    }
}
