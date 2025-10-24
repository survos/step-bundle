<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

/** Run only if the directory exists and is NOT empty. */
final class IfDirNotEmpty extends AbstractFsGuard
{
    protected function passes(string $absPath): bool
    {
        if (!@is_dir($absPath)) { return false; }
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
