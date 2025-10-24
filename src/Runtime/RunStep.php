<?php declare(strict_types=1);

namespace Survos\StepBundle\Runtime;

use Castor\Context;
use function Castor\io;
use Survos\StepBundle\Action\ExecutableAction;

final class RunStep
{
    /**
     * @param iterable<ExecutableAction> $actions
     */
    public static function run(iterable $actions, Context $ctx, bool $dryRun = false): void
    {
        foreach ($actions as $a) {
            io()->section($a->summary());
            $a->execute($ctx, $dryRun);
        }
    }
}
