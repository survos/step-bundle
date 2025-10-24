<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

interface RendersWithTwig
{
    /** e.g. 'bash.html.twig' or '@SurvosStep/step/action/bash.html.twig' */
    public function viewTemplate(): string;

    /** @return array<string,mixed> */
    public function viewContext(): array;
}
