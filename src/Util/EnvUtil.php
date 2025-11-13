<?php

declare(strict_types=1);

namespace Survos\StepBundle\Util;

final class EnvUtil
{
    /**
     * Load key=>value map from .env.local (or given filename).
     * Non KEY=VAL lines are ignored. Comments not preserved.
     */
    public static function readEnvLocal(string $projectDir, string $envFilename = '.env.local'): array
    {
        $projectDir = rtrim($projectDir, '/');
        $envFile = $projectDir . '/' . $envFilename;

        if (!is_dir($projectDir)) {
            throw new \RuntimeException("Project dir does not exist: $projectDir");
        }
        if (!is_file($envFile)) {
            return [];
        }

        $map = [];
        foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/', $line, $m)) {
                $map[$m[1]] = $m[2];
            }
        }
        return $map;
    }

    /**
     * Merge/overwrite KEY=VAL into .env.local (creates file if missing).
     * Minimal quoting: wraps value in quotes if it contains a space.
     */
    public static function writeEnvLocal(string $projectDir, array $pairs, string $envFilename = '.env.local'): void
    {
        $projectDir = rtrim($projectDir, '/');
        $envFile = $projectDir . '/' . $envFilename;

        if (!is_dir($projectDir)) {
            throw new \RuntimeException("Project dir does not exist: $projectDir");
        }

        $map = self::readEnvLocal($projectDir, $envFilename);
        foreach ($pairs as $k => $v) {
            $map[$k] = (string) $v;
        }

        $buf = '';
        foreach ($map as $k => $v) {
            $v = str_contains($v, ' ') ? "\"$v\"" : $v;
            $buf .= "$k=$v\n";
        }
        file_put_contents($envFile, $buf);
    }

    /**
     * Ensure required env vars exist; optionally prompt via $ask callback.
     *
     * $spec example:
     * [
     *   'OPENAI_API_KEY' => ['prompt' => 'Enter OPENAI_API_KEY', 'hidden' => true],
     *   'MEILISEARCH_API_KEY' => ['prompt' => 'Enter Meili key']
     * ]
     *
     * $ask signature: fn (string $prompt, bool $hidden): ?string
     * Returns the pairs that were (newly) written.
     */
    public static function ensureEnvVars(
        string $projectDir,
        array $spec,
        ?callable $ask = null,
        string $envFilename = '.env.local'
    ): array {
        $existing = self::readEnvLocal($projectDir, $envFilename);
        $toWrite = [];

        foreach ($spec as $key => $cfg) {
            $have = array_key_exists($key, $existing) && $existing[$key] !== '';
            if ($have) {
                continue;
            }
            $prompt = is_array($cfg) ? ($cfg['prompt'] ?? "Enter value for $key") : "Enter value for $key";
            $hidden = is_array($cfg) ? (bool)($cfg['hidden'] ?? false) : false;

            if (!$ask) {
                throw new \RuntimeException("Missing $key and no prompt callback provided. ($prompt)");
            }

            $val = $ask($prompt, $hidden, $cfg['default'] ?? null);
            if ($val === null || $val === '') {
                // Caller can decide if empty is acceptable; we skip writing empties here.
                continue;
            }
            $toWrite[$key] = $val;
        }

        if ($toWrite) {
            self::writeEnvLocal($projectDir, $toWrite, $envFilename);
        }

        return $toWrite;
    }

    /**
     * Perform a preg_replace on a file's contents.
     * Returns the number of replacements made.
     *
     * Example:
     * EnvUtil::pregReplaceInFile(
     *     '#\[MeiliIndex\]#',
     *     "#[MeiliIndex(\n    primaryKey: 'sku',\n    searchable: ['title','description']\n)]",
     *     'src/Entity/Movie.php'
     * );
     */
    public static function pregReplaceInFile(
        string $pattern,
        string $replacement,
        string $filePath,
        int $limit = -1
    ): int {
        if (!is_file($filePath)) {
            throw new \RuntimeException("File does not exist: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: $filePath");
        }

        $newContent = preg_replace($pattern, $replacement, $content, $limit, $count);

        if ($newContent === null) {
            throw new \RuntimeException("preg_replace failed for pattern: $pattern");
        }

        if ($count > 0) {
            if (file_put_contents($filePath, $newContent) === false) {
                throw new \RuntimeException("Failed to write file: $filePath");
            }
        }

        return $count;
    }
}
