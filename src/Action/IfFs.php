<?php declare(strict_types=1);

// file: src/Action/IfFs.php
namespace Survos\StepBundle\Action;

use Castor\Context;
use Symfony\Component\Filesystem\Path;
use function Castor\fs;

/**
 * Contract (informal): any Action class may implement toCommand(Context): string|array|null
 * - string|array => run via Castor\run()
 * - null         => skip (condition not met)
 */
interface ToCommandConvertible
{
    /** @return string|array|null */
    public function toCommand(Context $ctx): string|array|null;
}

/**
 * Base class for FS guards: resolves relative paths using the task's workingDirectory.
 */
abstract class AbstractFsGuard implements ToCommandConvertible
{
    public function __construct(
        protected readonly object $inner,
        protected readonly string $path
    ) {}

    /** Convert relative path to absolute, based on the Castor context workingDirectory. */
    protected function abs(Context $ctx): string
    {
        $wd = $ctx->workingDirectory ?? getcwd();
        return Path::isAbsolute($this->path) ? $this->path : rtrim($wd, '/').'/'.$this->path;
    }

    /** Run or skip depending on condition. */
    final public function toCommand(Context $ctx): string|array|null
    {
        $abs = $this->abs($ctx);
        if (!$this->passes($abs)) {
            // Skip when condition not satisfied.
            return null;
        }

        // Delegate to wrapped Action
        if (method_exists($this->inner, 'toCommand')) {
            /** @var string|array|null $spec */
            $spec = $this->inner->toCommand($ctx);
            return $spec;
        }

        // Or treat the wrapped thing as a raw command spec
        if (is_string($this->inner) || is_array($this->inner)) {
            return $this->inner;
        }

        // Unknown inner: skip rather than blow up
        return null;
    }

    /** Child classes implement the actual check. */
    abstract protected function passes(string $absPath): bool;
}

/** Run only if the file exists. */
final class IfFileExists extends AbstractFsGuard
{
    protected function passes(string $absPath): bool
    {
        return fs()->exists($absPath) && is_file($absPath);
    }
}

/** Run only if the file is missing. */
final class IfFileMissing extends AbstractFsGuard
{
    protected function passes(string $absPath): bool
    {
        return !fs()->exists($absPath);
    }
}

/** Run only if the directory is empty (no entries except . and ..). */
final class IfDirEmpty extends AbstractFsGuard
{
    protected function passes(string $absPath): bool
    {
        if (!is_dir($absPath)) {
            // treat non-existent as "empty" to allow scaffolding
            return true;
        }
        $dh = @opendir($absPath);
        if (!$dh) { return true; }
        try {
            while (false !== ($e = readdir($dh))) {
                if ($e === '.' || $e === '..') { continue; }
                return false;
            }
        } finally {
            @closedir($dh);
        }
        return true;
    }
}

/** Run only if the directory exists and is NOT empty. */
final class IfDirNotEmpty extends AbstractFsGuard
{
    protected function passes(string $absPath): bool
    {
        if (!is_dir($absPath)) { return false; }
        $dh = @opendir($absPath);
        if (!$dh) { return false; }
        try {
            while (false !== ($e = readdir($dh))) {
                if ($e === '.' || $e === '..') { continue; }
                return true;
            }
        } finally {
            @closedir($dh);
        }
        return false;
    }
}
