<?php declare(strict_types=1);

namespace Survos\StepBundle\Service;

use Survos\StepBundle\Action\RendersWithTwig;
use Twig\Environment;

final class ActionRenderer
{
    public function __construct(private readonly Environment $twig) {}

    /**
     * @param array<string,mixed> $context
     */
    public function renderAction(array $context, object $action): string
    {
        if (!$action instanceof RendersWithTwig) {
            throw new \LogicException(sprintf('Action %s does not implement RendersWithTwig', get_debug_type($action)));
        }

        $tpl = $action->viewTemplate();
        if (!str_starts_with($tpl, '@')) {
            $tpl = '@SurvosStep/step/action/' . ltrim($tpl, '/');
        }

        $loader = $this->twig->getLoader();
        if (!method_exists($loader, 'exists') || !$loader->exists($tpl)) {
            throw new \InvalidArgumentException(sprintf('Twig template "%s" not found (action %s)', $tpl, get_debug_type($action)));
        }

        $vars = ['action' => $action, 'type' => (new \ReflectionClass($action))->getShortName()];
        $vars += $action->viewContext();

        return $this->twig->render($tpl, $vars);
    }
}
