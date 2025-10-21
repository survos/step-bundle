<?php

declare(strict_types=1);

namespace Survos\StepBundle\Util;

use Symfony\Component\Yaml\Yaml;

final class YamlUtil
{
    /**
     * Deep-merge $patch into YAML file at $yamlPath.
     * Creates parent directory and file if missing.
     */
    public static function mergeFile(string $yamlPath, array $patch, int $dumpFlags = 0): void
    {
        $dir = \dirname($yamlPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $data = is_file($yamlPath) ? (Yaml::parseFile($yamlPath) ?? []) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $merged = self::deepMerge($data, $patch);
        file_put_contents($yamlPath, Yaml::dump($merged, 8, 2, $dumpFlags));
    }

    /**
     * Simple recursive array merge where scalar values in $b override $a.
     */
    public static function deepMerge(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            if (is_array($v) && isset($a[$k]) && is_array($a[$k])) {
                $a[$k] = self::deepMerge($a[$k], $v);
            } else {
                $a[$k] = $v;
            }
        }
        return $a;
    }
}
