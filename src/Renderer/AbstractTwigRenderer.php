<?php declare(strict_types=1);

namespace Survos\StepBundle\Renderer;

use Survos\StepBundle\Action\AbstractAction;
use Survos\StepBundle\Action\DisplayCode;
use Twig\Environment;

abstract class AbstractTwigRenderer implements ActionRendererInterface
{
    public function __construct(
        protected readonly Environment $twig,
        protected readonly string $projectDir,
    ) {}

    public function render(AbstractAction $action, array $context): string
    {
        // Enrich action data based on type
        $renderData = $this->enrichRenderData($context, $action);

        // Get template - subclasses can override getTemplatePath()
        $template = $this->getTemplatePath($action);

        // Verify template exists
        $loader = $this->twig->getLoader();
        if (!$loader->exists($template)) {
            throw new \InvalidArgumentException(sprintf(
                'Template "%s" not found for action %s in renderer %s',
                $template,
                $action::class,
                $this->getName()
            ));
        }

        // Render with renderer-specific wrapper
        $html = $this->twig->render($template, $renderData + [
            'action' => $action,
            'renderer' => $this->getName(),
        ]);
        return $html;
        // only if debug is enabled?
        return $this->wrapOutput($html, $action, $template, $renderData);
    }

    /**
     * Get the Twig template path for this action.
     * Override to customize template selection per renderer.
     */
    protected function getTemplatePath(AbstractAction $action): string
    {
        return '@SurvosStep/step/action/' . $action->viewTemplate();
    }

    /**
     * Wrap or modify the rendered output (for debug info, containers, etc.)
     */
    protected function wrapOutput(string $html, AbstractAction $action, string $template, array $data): string
    {
        return $html;
    }

    protected function enrichRenderData(array $context, AbstractAction $action): array
    {
        $data = $action->viewContext();

        // Special handling for DisplayCode with artifact: paths
        if ($action instanceof DisplayCode) {
            $data = $this->resolveDisplayCodeArtifact($context, $data);

            // Load code from filesystem if needed
            if (empty($data['code']) && !empty($data['path'])) {
                $code = $this->loadCodeFromPath($data['path']);
                if ($code !== null) {
                    $data['code'] = $code;
                    if (empty($data['lang'])) {
                        $data['lang'] = $this->guessLang($data['path']);
                    }
                }
            }
        }

        return $data;
    }

    protected function resolveDisplayCodeArtifact(array $context, array $data): array
    {
        $path = $data['path'] ?? null;
        if (!is_string($path) || !str_starts_with($path, 'artifact:')) {
            return $data;
        }

        $spec = substr($path, strlen('artifact:'));
        $parts = explode('::', $spec, 3);
        if (count($parts) !== 3) {
            return $data;
        }

        [$stepTitle, $actionId, $fileName] = $parts;
        $taskName = (string)($context['__task_name'] ?? $context['code'] ?? 'slide');
        $stepTitle = (string)($context['__step_title'] ?? $stepTitle);

        $safeTask = preg_replace('/[^A-Za-z0-9._-]+/', '-', $taskName) ?: 'slide';
        $safeStep = preg_replace('/[^A-Za-z0-9._-]+/', '-', $stepTitle) ?: 'step';
        $safeAction = 'console-' . (preg_replace('/[^A-Za-z0-9._-]+/', '-', $actionId) ?: 'action');

        $data['path'] = "/artifacts/{$safeTask}/{$safeStep}/files/{$safeAction}/{$fileName}";
        return $data;
    }

    protected function loadCodeFromPath(string $path): ?string
    {
        $abs = null;
        if (str_starts_with($path, '/')) {
            $abs = rtrim($this->projectDir, '/') . '/public' . $path;
        } elseif (is_file($path)) {
            $abs = $path;
        }

        if ($abs && is_file($abs)) {
            $content = @file_get_contents($abs);
            return $content === false ? null : $content;
        }

        return null;
    }

    protected function guessLang(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'php' => 'php',
            'yml', 'yaml' => 'yaml',
            'twig' => 'twig',
            'json' => 'json',
            'html', 'htm' => 'html',
            'md', 'markdown' => 'markdown',
            'js' => 'javascript',
            'ts' => 'typescript',
            'css' => 'css',
            'sql' => 'sql',
            'bash', 'sh' => 'bash',
            default => 'text',
        };
    }
}
