<?php declare(strict_types=1);

namespace Survos\StepBundle\Renderer;

use Survos\StepBundle\Action\AbstractAction;

interface ActionRendererInterface
{
    /**
     * Render an action to a string (HTML, Markdown, etc.)
     * @param array<string,mixed> $context Twig context for artifact resolution
     */
    public function render(AbstractAction $action, array $context): string;
    
    /**
     * Get a unique identifier for this renderer (e.g., 'debug', 'reveal', 'markdown')
     */
    public function getName(): string;
}
