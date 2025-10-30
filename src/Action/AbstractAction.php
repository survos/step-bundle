<?php declare(strict_types=1);
// src/Action/AbstractAction.php

namespace Survos\StepBundle\Action;

abstract class AbstractAction implements ExecutableAction, RendersWithTwig
{
    public function viewContext(): array { return []; }
    public function toCommand(): ?string { return isset($this->command) ? $this->command : null; }

    public string $highlightLanguage = 'bash';
}
