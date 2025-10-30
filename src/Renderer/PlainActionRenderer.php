<?php declare(strict_types=1);

namespace Survos\StepBundle\Renderer;

/**
 * Minimal renderer for production/documentation use
 */
final class PlainActionRenderer extends AbstractTwigRenderer
{
    public function getName(): string
    {
        return 'plain';
    }
    
    // No wrapping, just pure template output
}
