<?php declare(strict_types=1);

// src/Twig/StepRuntimeExtension.php

namespace Survos\StepBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Renders actions that may arrive as native objects or JSON-decoded arrays/stdClass.
 * For ComposerRequire / ImportmapRequire, prints a left-justified bash block.
 */
final class StepRuntimeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_action', [$this, 'renderAction'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array|object $action
     */
    public function renderAction($action): string
    {
        $arr  = $this->toArray($action);
        $type = (string)($arr['type'] ?? '');

        if ($type === 'ComposerRequire' || $type === 'ImportmapRequire') {
            $cmd = (string)($arr['multiline'] ?? $this->rebuildMultiline($arr));
            $cmd = htmlspecialchars($cmd, ENT_QUOTES);
            return "<div class=\"code-wrap\"><pre><code class=\"language-bash\">{$cmd}</code></pre></div>";
        }

        $cwd  = isset($arr['cwd']) && $arr['cwd'] ? ' (cwd: ' . htmlspecialchars((string)$arr['cwd'], ENT_QUOTES) . ')' : '';
        $safeType = htmlspecialchars($type ?: 'Action', ENT_QUOTES);
        return "<div class=\"code-wrap\"><pre><code>{$safeType}{$cwd}</code></pre></div>";
    }

    private function toArray($a): array
    {
        if (\is_array($a)) return $a;
        if ($a instanceof \stdClass) return (array)$a;
        if (\is_object($a)) return get_object_vars($a);
        return [];
    }

    private function rebuildMultiline(array $arr): string
    {
        $type = (string)($arr['type'] ?? '');
        $requires = \is_array($arr['requires'] ?? null) ? $arr['requires'] : [];
        $lines = [];

        $head = match ($type) {
            'ComposerRequire'  => 'composer req' . (!empty($arr['dev']) ? ' --dev' : ''),
            'ImportmapRequire' => 'console importmap:require',
            default            => 'sh',
        };

        foreach ($requires as $r) {
            if (!\is_array($r)) continue;
            $pkg   = (string)($r['package'] ?? '');
            $con   = $r['constraint'] ?? null;
            $label = $con ? "{$pkg}:{$con}" : $pkg;
            $cmt   = isset($r['comment']) && $r['comment'] !== null && $r['comment'] !== '' ? ' # ' . $r['comment'] : '';
            $lines[] = sprintf('       %s \\%s', $label, $cmt);
        }

        return $lines ? $head . " \\\n" . implode("\n", $lines) : $head;
    }
}
