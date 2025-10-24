<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

/** Run only if the file exists. */
final class IfFileExists extends AbstractFsGuard
{
    protected function passes(string $absPath): bool
    {
        return $this->isFile($absPath);
    }
}
