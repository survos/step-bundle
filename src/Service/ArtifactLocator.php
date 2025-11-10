<?php
namespace Survos\StepBundle\Service;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;

final class ArtifactLocator
{
    private string $projectDir;
    private string $subpath;

    public function __construct(
        ?KernelInterface $kernel = null,
        ?string $projectDir = null,
        string $subpath = 'public/artifacts'
    ) {
        // Priority: explicit projectDir → Kernel → env/const/helpers → getcwd()
        $this->projectDir = $projectDir
            ?? ($kernel?->getProjectDir())
            ?? (getenv('STEPS_PROJECT_DIR') ?: null)
            ?? (defined('STEPS_PROJECT_DIR') ? STEPS_PROJECT_DIR : null)
            ?? (function_exists('steps_project_dir') ? steps_project_dir() : null)
            ?? getcwd();

        $this->subpath = trim($subpath, '/');
    }

    /** Factory for Symfony service definition */
    public static function fromKernel(KernelInterface $kernel, string $subpath = 'public/artifacts'): self
    {
        return new self($kernel, null, $subpath);
    }

    /** Factory for CLI/Castor bootstrapping */
    public static function fromBaseDir(string $projectDir, string $subpath = 'public/artifacts'): self
    {
        return new self(null, rtrim($projectDir, '/'), $subpath);
    }

    /** Last-resort CLI bootstrap (checks env/const and __DIR__) */
    public static function bootstrapForCli(?string $fallbackDir = null, string $subpath = 'public/artifacts'): self
    {
        $base = getenv('STEPS_PROJECT_DIR')
            ?: (defined('STEPS_PROJECT_DIR') ? STEPS_PROJECT_DIR : null)
                ?: (function_exists('steps_project_dir') ? steps_project_dir() : null)
                    ?: $fallbackDir
                        ?: getcwd();

        return self::fromBaseDir($base, $subpath);
    }

    public function absolute(string $projectCode, string $rel): string
    {
        return Path::join($this->projectDir, $this->subpath, $projectCode, ltrim($rel, '/'));
    }
    public function relative(string $projectCode, string $rel): string
    {
        return str_replace('public', '',
            Path::join($this->subpath, $projectCode, ltrim($rel, '/'))
        );
    }

    public function exists(string $projectCode, string $rel): bool
    {
        return is_file($this->absolute($projectCode, $rel));
    }

    public function read(string $projectCode, string $rel): ?string
    {
        $abs = $this->absolute($projectCode, $rel);
        return is_file($abs) ? file_get_contents($abs) : null;
    }

    public function write(string $projectCode, string $rel, string $contents): string
    {
        $abs = $this->absolute($projectCode, $rel);
        @mkdir(dirname($abs), 0777, true);
        if (false === file_put_contents($abs, $contents)) {
            throw new \RuntimeException("Failed to write artifact: $abs");
        }
        return $abs;
    }

    /** Turn an absolute file path back into a public path (for links) */
    public function publishPath(string $absPath): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($absPath, strlen($this->projectDir)));
    }

    // relative to /public, for image tags
    public function baseDir(): string
    {
        // hack!
        return $this->subpath;
    }
    public function publicPath(): string
    {
        return $this->projectDir;
    }

}
