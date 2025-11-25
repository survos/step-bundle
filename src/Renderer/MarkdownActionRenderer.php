<?php declare(strict_types=1);

namespace Survos\StepBundle\Renderer;

use Survos\StepBundle\Action\AbstractAction;

final class MarkdownActionRenderer extends AbstractTwigRenderer
{
    public function getName(): string
    {
        return 'markdown';
    }

    protected function wrapOutput(string $html, AbstractAction $action, string $template, array $data): string
    {
        $debugInfo = sprintf(
            '<div class="action-debug" style="border:1px solid #30405e; padding:8px; margin:8px 0; background:#0b0e18; border-radius:6px">'
            . '<div style="font:11px/1.4 monospace; color:#8aa0b4; margin-bottom:6px">'
            . '<strong style="color:#74c0fc">%s</strong> → %s<br>'
            . 'Template: <code style="color:#51cf66">%s</code><br>'
            . 'Data keys: <code>%s</code>'
            . '</div>'
            . '%s'
            . '</div>',
            htmlspecialchars(new \ReflectionClass($action::class)->getShortName()),
            htmlspecialchars($action::class),
            htmlspecialchars($template),
            htmlspecialchars(implode(', ', array_keys($data))),
            $html
        );

        return $debugInfo;
    }
}
