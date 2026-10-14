<?php

declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;

/**
 * Run a Symfony console command in a slideshow-friendly way.
 *
 * Examples:
 *   new Console('make:controller', ['App']);
 *   new Console('make:entity', ['Task'], prefer: 'symfony'); // uses "symfony console" if available
 *   new Console('doctrine:migrations:migrate', ['-n'], env: ['APP_ENV' => 'dev']);
 *
 * Strategy:
 *   $prefer = 'auto'    → use "symfony console" if available, else "php bin/console"
 *   $prefer = 'symfony' → try "symfony console", fallback to "php bin/console"
 *   $prefer = 'php'     → always "php bin/console"
 *
 * Like ComposerRequire, this action delegates execution to Bash internally for consistency.
 */
final class Console extends AbstractAction
{
    /** @var string[] */
    public array $args;

    /** @param string[] $args */
    public function __construct(
        public string $command,
        array $args = [],
        public ?string $note = null,
        public ?string $cwd = null,
        public array $env = [], // ??
        public ?string $a = null, // artifact
        public string $prefer = 'auto',    // 'auto' | 'symfony' | 'php'
        public string $consolePath = 'bin/console',
        public string $phpBinary = 'php',
        public bool $autoNoInteraction = true,
        public ?string $postfix = null, // e.g. >> 'env.local'
        public ?int $size = null,
    ) {
        $this->args = array_values($args);
        $this->size ??= 3;
    }

    public function toCommand(): ?string
    {
        return $this->consolePath.' '.$this->command;
    }

    public function summary(): string
    {
        $prefix = $this->resolvePrefixForSummary();
        $cmd = $this->command;
        $args = implode(' ', array_map([$this, 'escapeArgForSummary'], $this->args));

        return trim(sprintf('%s %s %s', $prefix, $cmd, $args));
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        $argv = $this->buildArgv($ctx);

        // Build a Bash-safe string for execution while preserving argv semantics.
        // We join with escapeshellarg to avoid shell injection in preview/execute.
        $code = implode(' ', array_map('escapeshellarg', $argv));
        if ($this->postfix) {
            $code .= ' '.$this->postfix;
        }
        // Prefer running with env + cwd through Bash action (shared runner, unified logging).
        $bash = new Bash($code, $this->note, $this->cwd, a: $this->a);
        $bash->execute($ctx, $dryRun);
    }

    public function viewTemplate(): string
    {
        return 'console.html.twig';
    }

    public function viewContext(): array
    {
        return [
            'code' => $this->summary(),
            'lang' => 'bash',
            'cmd' => $this->command,
            'args' => $this->args,
            'note' => $this->note,
            'cwd' => $this->cwd,
            'env' => $this->env,
            'prefer' => $this->prefer,
        ];
    }

    // ---------- internals ----------

    /**
     * Whether to pass -n when running: commands such as make:migration and
     * doctrine:migrations:migrate otherwise wait for an answer nobody can give.
     * Decided at run time so slides show the command as written, without -n.
     */
    private function needsNoInteraction(): bool
    {
        if (!$this->autoNoInteraction) {
            return false;
        }

        return [] === array_intersect($this->args, ['-n', '--no-interaction']);
    }

    /** @return string[] tokenized argv like ["symfony","console","make:entity","Task","-n"] */
    private function buildArgv(Context $ctx): array
    {
        $wd = $ctx->workingDirectory ?? getcwd();

        $useSymfony = match ($this->prefer) {
            'php' => false,
            'symfony' => true,
            default => $this->symfonyAvailable(),
        };

        if ($useSymfony) {
            $argv = ['symfony', 'console', $this->command];
        } else {
            $console = $this->resolveConsolePath($wd);
            $argv = [$this->phpBinary, $console, $this->command];
        }

        foreach ($this->args as $a) {
            $argv[] = $a;
        }
        if ($this->needsNoInteraction()) {
            $argv[] = '-n';
        }

        return $argv;
    }

    private function resolvePrefixForSummary(): string
    {
        if ('php' === $this->prefer) {
            return sprintf('%s %s', $this->phpBinary, $this->consolePath);
        }
        if ('symfony' === $this->prefer || $this->symfonyAvailable()) {
            return 'symfony console';
        }

        return sprintf('%s %s', $this->phpBinary, $this->consolePath);
    }

    private function escapeArgForSummary(string $arg): string
    {
        // nice-looking summary; keep simple if alnum/-, otherwise quote
        return preg_match('/^[A-Za-z0-9._:=\/@,+-]+$/', $arg) ? $arg : escapeshellarg($arg);
    }

    private function symfonyAvailable(): bool
    {
        $exit = 1;
        @exec('command -v symfony >/dev/null 2>&1', $_, $exit);

        return 0 === $exit;
    }

    private function resolveConsolePath(string $workingDir): string
    {
        if ($this->isAbsolutePath($this->consolePath)) {
            return $this->consolePath;
        }

        return rtrim($workingDir, '/').'/'.$this->consolePath;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || 1 === preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}
