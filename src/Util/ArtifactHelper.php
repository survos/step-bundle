<?php declare(strict_types=1);

namespace Survos\StepBundle\Util;

use Castor\Context;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Centralized artifact saving. Uses task()->getName() and context()->workingDirectory.
 * Folder layout:
 *   public/artifacts/<safeTask>/<safeStep>/<actionKey>/{logs,diff,files/...}
 */
final class ArtifactHelper
{
    public function __construct(
        private readonly string $projectDir,      // working dir from context()
        private readonly string $safeTask,        // sanitized task name
        private readonly string $safeStep         // sanitized current Step title
    ) {}

    public static function fromTaskContext(?\Castor\Console\Command\TaskCommand $task, ?Context $ctx): self
    {
        $wd = (string)($ctx?->workingDirectory ?? getcwd());
        if (!is_dir($wd)) {
            throw new \RuntimeException("Working directory not found: $wd");
        }
        $taskName = $task?->getName() ?: 'slide';
        return new self($wd, self::safe($taskName), self::safe('step'));
    }

    public function withStep(string $stepTitle): self
    {
        return new self($this->projectDir, $this->safeTask, self::safe($stepTitle));
    }

    public function baseDir(): string
    {
        return $this->projectDir . '/public/artifacts/' . $this->safeTask . '/' . $this->safeStep;
    }

    public function actionDir(string $actionKey): string
    {
        return $this->baseDir() . '/' . self::safe($actionKey);
    }

    public function save(string $relativePath, string $contents): string
    {
        $abs = $this->baseDir() . '/' . ltrim($relativePath, '/');
        $this->ensureDir(\dirname($abs));
        if (false === file_put_contents($abs, $contents)) {
            throw new IOException("Failed to write artifact: $abs");
        }
        return $abs;
    }

    public function saveFile(string $relativePath, string $sourceAbsPath): string
    {
        if (!is_file($sourceAbsPath)) {
            throw new \RuntimeException("Source not found: $sourceAbsPath");
        }
        $abs = $this->baseDir() . '/' . ltrim($relativePath, '/');
        $this->ensureDir(\dirname($abs));
        if (!copy($sourceAbsPath, $abs)) {
            throw new IOException("Failed to copy artifact to: $abs");
        }
        return $abs;
    }

    public function publishPath(string $absPath): string
    {
        $rel = substr($absPath, strlen($this->projectDir));
        return str_replace(DIRECTORY_SEPARATOR, '/', $rel);
    }

    public static function safe(string $s): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $s) ?: 'x';
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new IOException("Failed to create directory: $dir");
        }
    }
}
