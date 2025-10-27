<?php
// src/Util/ArtifactWriter.php
declare(strict_types=1);

namespace Survos\StepBundle\Util;

use Castor\Context;
use function Castor\{context, task};

final class ArtifactWriter
{
    private string $projectDir;
    private string $slide;

    public function __construct(?Context $ctx = null, ?string $slide = null)
    {
        $ctx = $ctx ?? context();
        $wd  = (string)($ctx->workingDirectory ?? getcwd());
        if (!is_dir($wd)) {
            throw new \RuntimeException("Working directory not found: $wd");
        }
        $this->projectDir = $wd;

        $taskName = null;
        try {
            $t = task();
            $taskName = method_exists($t, 'getName') ? (string)$t->getName() : null;
        } catch (\Throwable) { /* optional */ }

        $this->slide = $this->safe($slide ?? $taskName ?? 'slide');
    }

    public function save(string $relName, string $contents, string $type): string
    {
        [$root, $pub] = [$this->projectDir . '/var/castor-artifacts', $this->projectDir . '/public/artifacts'];
        $this->ensureDir($root);
        $this->ensureDir($pub);

        $privDir = "$root/{$this->slide}";
        $pubDir  = "$pub/{$this->slide}";
        $this->ensureDir($privDir);
        $this->ensureDir($pubDir);

        $privFile = "$privDir/$relName";
        $pubFile  = "$pubDir/$relName";
        $this->ensureDir(\dirname($privFile));
        $this->ensureDir(\dirname($pubFile));

        file_put_contents($privFile, $contents);
        if (!copy($privFile, $pubFile)) {
            throw new \RuntimeException("Failed to publish artifact to $pubFile");
        }

        return "/artifacts/{$this->slide}/{$relName}";
    }

    public function slide(): string { return $this->slide; }

    private function safe(string $s): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $s) ?: 'slide';
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) return;
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: $dir");
        }
    }
}
