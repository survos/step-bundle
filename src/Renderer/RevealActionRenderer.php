<?php declare(strict_types=1);

namespace Survos\StepBundle\Renderer;

use Survos\StepBundle\Action\AbstractAction;

final class RevealActionRenderer extends AbstractTwigRenderer
{
    public function getName(): string
    {
        return 'reveal';
    }

    protected function getTemplatePath(AbstractAction $action): string
    {
        // Use reveal-specific templates if they exist, otherwise fall back to default
        $revealTemplate = '@SurvosStep/step/action/reveal/' . $action->getTemplateName();
        $defaultTemplate = '@SurvosStep/step/action/' . $action->getTemplateName();
        
        $loader = $this->twig->getLoader();
        if (method_exists($loader, 'exists') && $loader->exists($revealTemplate)) {
            return $revealTemplate;
        }
        
        return $defaultTemplate;
    }

    protected function wrapOutput(string $html, AbstractAction $action, string $template, array $data): string
    {
        // Wrap in reveal-specific code-card structure
        return sprintf(
            '<div class="code-card">%s</div>',
            $html
        );
    }
}
