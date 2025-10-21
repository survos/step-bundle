<?php

declare(strict_types=1);

namespace Survos\StepBundle\Util;

final class SnippetUtil
{
    /**
     * Returns a Markdown code fence with an optional title line.
     * Example:
     *   io()->writeln(SnippetUtil::fenced($code, 'php', '__invoke example'));
     */
    public static function fenced(string $code, string $language = 'php', ?string $title = null): string
    {
        $titleLine = $title ? "### {$title}\n\n" : '';
        return $titleLine . "```{$language}\n{$code}\n```\n";
    }
}
