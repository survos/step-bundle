<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

/** Run only if the file is missing (or the path does not exist). */
final class IfFileMissing extends AbstractFsGuard
{
    protected function passes(string $absPath): bool
    {
        return !$this->exists($absPath);
    }
}
