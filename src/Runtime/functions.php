<?php declare(strict_types=1);
// File: src/Runtime/functions.php
namespace Survos\StepBundle\Runtime;

function run_step(array $options = []): void
{
    RunStep::run($options);
}
