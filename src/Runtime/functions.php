<?php // append to src/Runtime/functions.php
declare(strict_types=1);

namespace Survos\StepBundle\Runtime;

use Castor\Console\Command\TaskCommand;
use Survos\StepBundle\Metadata\Step;
use ReflectionAttribute;
use ReflectionFunction;

/**
 * @return array<object>  The actions from all #[Step] attributes on the callable.
 */
function actions_for_callable(callable|string $callable): array
{
    $rf = new ReflectionFunction($callable);
    $steps = $rf->getAttributes(Step::class, ReflectionAttribute::IS_INSTANCEOF);
    $out = [];
    foreach ($steps as $attr) {
        /** @var Step $step */
        $step = $attr->newInstance();
        // Keep the raw action objects (polymorphic)
        foreach ($step->actions as $a) {
            $out[] = $a;
        }
    }
    return $out;
}

/**
 * Extract actions from the current Castor task.
 * @return array<object>
 */
function actions_from_task(TaskCommand $taskCmd): array
{
    $callable = $taskCmd->getTask()->getCallable();
    return actions_for_callable($callable);
}
