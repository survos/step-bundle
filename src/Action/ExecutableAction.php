<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;

interface ExecutableAction
{
    public function summary(): string;
    public function execute(Context $ctx, bool $dryRun = false): void;
}
