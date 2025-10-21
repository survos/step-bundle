<?php

declare(strict_types=1);

namespace Survos\StepBundle\Util;

final class PathUtil
{
    /**
     * Return absolute path; if $path is relative, resolve against $base.
     * If target exists, prefer realpath() to resolve symlinks and '..'.
     */
    public static function absPath(string $path, string $base): string
    {
        $isAbsolute = str_starts_with($path, '/')
            || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':');

        if ($isAbsolute) {
            return $path;
        }

        $candidate = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $path;

        $real = @realpath($candidate);
        if ($real !== false) {
            return $real;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, $normalized) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $seg;
        }
        $prefix = (str_starts_with($normalized, DIRECTORY_SEPARATOR)) ? DIRECTORY_SEPARATOR : '';
        return $prefix . implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * Ensure a directory exists (mkdir -p).
     */
    public static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
