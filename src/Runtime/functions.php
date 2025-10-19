<?php declare(strict_types=1);
/**
 * File: src/Runtime/functions.php
 */
namespace Survos\StepBundle\Runtime;

/**
 * Minimal helper so tasks can `use function Survos\StepBundle\Runtime\run_step;`
 * and simply call run_step();  The runner finds the caller via backtrace.
 */
function run_step(array $options = []): void
{
    RunStep::run($options);
}
