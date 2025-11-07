<?php declare(strict_types=1);
// src/Action/AbstractAction.php

namespace Survos\StepBundle\Action;

use Survos\StepBundle\Service\ArtifactLocator;

abstract class AbstractAction implements ExecutableAction, RendersWithTwig
{
//    private(set) ?string $note = null;
    public function viewContext(): array { return []; }
    public function toCommand(): ?string { return isset($this->command) ? $this->command : null; }

    public string $highlightLanguage = 'bash';
    public ?string $project = null; // e.g. ea, ezp, can come from _ENV or $request or CLI
    public ?ArtifactLocator $artifactLocator = null;
    public ?string $artifactId { get => property_exists($this, "a") ? $this->a : null; }


}
