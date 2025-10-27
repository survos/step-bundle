<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

/** Run only if the directory is empty (non-existent counts as empty). */
final class IfDirEmpty extends AbstractFsGuard
{
    protected function passes(string $absPath): bool
    {
        if (!@is_dir($absPath)) {
            return true; // treat missing dir as empty for scaffolding
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
