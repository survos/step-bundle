<?php

declare(strict_types=1);

namespace Survos\StepBundle\Service;

use Survos\StepBundle\Action\AbstractAction;
use Survos\StepBundle\Action\Bash;
use Survos\StepBundle\Action\ComposerRequire;
use Survos\StepBundle\Action\Console;
use Survos\StepBundle\Action\DisplayArtifact;
use Survos\StepBundle\Action\DisplayCode;
use Survos\StepBundle\Action\FileWrite;

/**
 * Turns an exported deck (CastorStepExporter::exportSlides()) into a Markdown document: the same steps the
 * slideshow shows, as an article. One section per step; commands and files as fenced code; image artifacts as
 * images, plus a mermaid block when a .mmd artifact sits next to the image (GitHub renders it; Medium does not).
 */
final class MarkdownDeckRenderer
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

    /**
     * @param array{code?:string,meta?:array<string,mixed>,slides?:list<array<string,mixed>>} $deck
     * @param string                                                                          $baseUrl prefix for image URLs, e.g. https://steps.example (empty: site-relative)
     */
    public function render(array $deck, string $baseUrl = ''): string
    {
        $meta = $deck['meta'] ?? [];
        $code = (string) ($deck['code'] ?? '');
        $title = '' !== ($meta['title'] ?? '') ? (string) $meta['title'] : ucwords(str_replace(['-', '_'], ' ', $code));

        $out = [];
        $out[] = "---\ntitle: ".$this->yaml($title)
            .(!empty($meta['subtitle']) ? "\nsubtitle: ".$this->yaml((string) $meta['subtitle']) : '')
            .(!empty($meta['author']) ? "\nauthor: ".$this->yaml((string) $meta['author']) : '')
            ."\n---";
        $out[] = '# '.$title;
        if (!empty($meta['subtitle'])) {
            $out[] = '*'.$meta['subtitle'].'*';
        }

        foreach ($deck['slides'] ?? [] as $slide) {
            $out[] = $this->slide($slide, rtrim($baseUrl, '/'));
        }

        return implode("\n\n", array_filter($out, static fn (string $s): bool => '' !== trim($s)))."\n";
    }

    /** @param array<string,mixed> $slide */
    private function slide(array $slide, string $baseUrl): string
    {
        $parts = [];
        $title = (string) ($slide['title'] ?? '') ?: (string) ($slide['task_name'] ?? '');
        $parts[] = '## '.$title;
        if ('' !== trim((string) ($slide['description'] ?? ''))) {
            $parts[] = trim((string) $slide['description']);
        }
        $bullets = array_values(array_filter((array) ($slide['bullets'] ?? []), 'is_string'));
        if ([] !== $bullets) {
            $parts[] = implode("\n", array_map(static fn (string $b): string => '- '.$b, $bullets));
        }
        foreach ($slide['actions'] ?? [] as $action) {
            if ($action instanceof AbstractAction) {
                $md = $this->action($action, $baseUrl);
                if ('' !== $md) {
                    $parts[] = $md;
                }
            }
        }

        return implode("\n\n", $parts);
    }

    private function action(AbstractAction $action, string $baseUrl): string
    {
        return match (true) {
            $action instanceof FileWrite => $this->file($action->path, $action->content, $action->note),
            $action instanceof Bash => $this->shell($action->commands ?: array_filter([$action->command]), $action->note ?? $action->description),
            $action instanceof Console => $this->shell([$action->summary()], $action->note),
            $action instanceof ComposerRequire => $this->shell(['composer require '.($action->dev ? '--dev ' : '').implode(' ', $action->packages)], $action->note ?? $action->description),
            $action instanceof DisplayArtifact => $this->artifact($action, $baseUrl),
            $action instanceof DisplayCode => $this->displayCode($action),
            default => '',
        };
    }

    /** @param list<string> $commands */
    private function shell(array $commands, ?string $note): string
    {
        $commands = array_values(array_filter($commands, static fn ($c): bool => \is_string($c) && '' !== trim($c)));
        if ([] === $commands) {
            return '';
        }

        return ($note ? $note."\n\n" : '').$this->fence('bash', implode("\n", $commands));
    }

    private function file(string $path, string $content, ?string $note): string
    {
        return ($note ? $note."\n\n" : '')."Create `{$path}`:\n\n".$this->fence(FileWrite::guessLangFromPath($path), rtrim($content));
    }

    private function displayCode(DisplayCode $action): string
    {
        if (null === $action->content || '' === $action->content) {
            return $action->path ? 'See `'.$action->path.'`.' : '';
        }

        return ($action->path ? "`{$action->path}`:\n\n" : '').$this->fence($action->lang ?? ($action->path ? FileWrite::guessLangFromPath($action->path) : ''), rtrim($action->content));
    }

    private function artifact(DisplayArtifact $action, string $baseUrl): string
    {
        $locator = $action->artifactLocator;
        $project = (string) $action->project;
        $ext = strtolower(pathinfo($action->path, \PATHINFO_EXTENSION));

        if (!\in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            $file = $locator?->absolute($project, $action->path);
            $content = $file && is_file($file) ? (string) file_get_contents($file) : '';

            return '' === $content ? '' : $this->fence($action->lang ?? $ext, rtrim($content));
        }

        $url = $baseUrl.($locator ? $locator->relative($project, $action->path) : '/'.$action->path);
        $md = '!['.($action->note ?? $action->path).']('.$url.')';

        $mermaid = $locator?->absolute($project, preg_replace('/\.[^.]+$/', '.mmd', $action->path));
        if ($mermaid && is_file($mermaid)) {
            $md .= "\n\n<details><summary>Diagram source (mermaid)</summary>\n\n".$this->fence('mermaid', rtrim((string) file_get_contents($mermaid)))."\n\n</details>";
        }

        return $md;
    }

    /** A fence longer than any run of backticks inside, so code containing ``` cannot close it early. */
    private function fence(string $lang, string $code): string
    {
        $ticks = '```';
        while (str_contains($code, $ticks)) {
            $ticks .= '`';
        }

        return $ticks.$lang."\n".$code."\n".$ticks;
    }

    private function yaml(string $s): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"';
    }
}
