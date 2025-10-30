<?php declare(strict_types=1);
// src/Twig/StepRuntimeExtension.php

namespace Survos\StepBundle\Twig;

use Survos\StepBundle\Action\AbstractAction;
use Survos\StepBundle\Renderer\ActionRendererInterface;
use Survos\StepBundle\Renderer\PlainActionRenderer;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StepRuntimeExtension extends AbstractExtension
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $projectDir,
        private ?ActionRendererInterface $defaultRenderer = null,
    ) {
        $this->defaultRenderer ??= new PlainActionRenderer($twig, $projectDir);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_action', [$this, 'renderAction'], [
                'is_safe' => ['html'],
                'needs_context' => true
            ]),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param ActionRendererInterface|null $renderer If null, uses default renderer
     */
    public function renderAction(
        array $context,
        AbstractAction $action,
        ?ActionRendererInterface $renderer = null
    ): string {
        $renderer ??= $this->defaultRenderer;
        return $renderer->render($action, $context);
    }
}
