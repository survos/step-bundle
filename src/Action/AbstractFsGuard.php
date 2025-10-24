<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use Symfony\Component\Filesystem\Path;
use function Castor\fs;

/**
 * Base class for filesystem guards that wrap another action.
 * Child classes implement passes(string $absPath): bool.
 */
abstract class AbstractFsGuard implements ToCommandConvertible
{
    public function __construct(
        protected readonly object|string $inner,
        protected readonly ?string $path=null
    ) {}

    /** Convert relative path to absolute, based on the task's workingDirectory. */
    protected function abs(Context $ctx): string
    {
        $wd = $ctx->workingDirectory ?? getcwd();
        return Path::isAbsolute($this->path) ? $this->path : rtrim($wd, '/').'/'.$this->path;
    }

    /** Child classes decide if the wrapped action should run. */
    abstract protected function passes(string $absPath): bool;

    /** @return string|array|null */
    final public function toCommand(Context $ctx): string|array|null
    {
        $abs = $this->abs($ctx);
        if (!$this->passes($abs)) {
            return null; // skip
        }

        // Delegate to wrapped action if it can convert itself
        if (method_exists($this->inner, 'toCommand')) {
            /** @var string|array|null $spec */
            $spec = $this->inner->toCommand($ctx);
            return $spec;
        }

        // Or treat the wrapped thing as a raw command spec
        if (is_string($this->inner) || is_array($this->inner)) {
            return $this->inner;
        }

        // Unknown inner: skip rather than fail
        return null;
    }

    /** Convenience helpers for children */
    protected function exists(string $absPath): bool     { return fs()->exists($absPath); }
    protected function isFile(string $absPath): bool     { return $this->exists($absPath) && @is_file($absPath); }
    protected function isDir(string $absPath): bool      { return @is_dir($absPath); }
}
