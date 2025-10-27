<?php declare(strict_types=1);

// file: src/Slideshow/SlideshowJsonl.php
namespace Survos\StepBundle\Slideshow;

use Survos\JsonlBundle\IO\JsonlWriter;

final class SlideshowJsonl
{
    /** @var array<string, JsonlWriter> */
    private static array $writers = [];

    public static function debug(): bool
    {
        return (($_ENV['SLIDESHOW_DEBUG'] ?? \getenv('SLIDESHOW_DEBUG') ?? '') === '1');
    }

    public static function outDir(): string
    {
        $dir = $_ENV['SLIDESHOW_OUT_DIR'] ?? \getenv('SLIDESHOW_OUT_DIR') ?: (\getcwd() . '/.castor/slideshows');
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0777, true);
            if (self::debug()) { \fwrite(\STDERR, "[sjl] created dir: {$dir}\n"); }
        }
        return $dir;
    }

    /**
     * Resolve the slideshow code.
     * Precedence: ENV(SLIDESHOW) → $hint → prefix of $taskName (before ':') → basename(guessCastorFile) → 'default'
     */
    public static function slideshowCode(?string $hint = null, ?string $taskName = null): string
    {
        // 1) explicit env override
        $env = $_ENV['SLIDESHOW'] ?? \getenv('SLIDESHOW');
        if (\is_string($env) && $env !== '') {
            return $env;
        }

        // 2) explicit hint from caller
        if (\is_string($hint) && $hint !== '') {
            return $hint;
        }

        // 3) derive from task name "prefix:task"
        if (\is_string($taskName) && $taskName !== '' && \str_contains($taskName, ':')) {
            $prefix = \explode(':', $taskName, 2)[0] ?? '';
            if ($prefix !== '') {
                return $prefix;
            }
        }

        // 4) basename of guessed castor file (strip .castor.php)
        $cf = self::guessCastorFile();
        if ($cf) {
            $base = \basename($cf, '.castor.php');
            if ($base !== '') {
                return $base;
            }
        }

        // 5) fallback
        return 'default';
    }

    /** Back-compat alias; kept if you previously used S::code() */
    public static function code(): string
    {
        return self::slideshowCode();
    }

    public static function filePath(?string $code = null): string
    {
        $code ??= self::slideshowCode();
        $path = \rtrim(self::outDir(), '/') . '/' . $code . '.jsonl';
        if (self::debug()) { \fwrite(\STDERR, "[sjl] filePath({$code}) => {$path}\n"); }
        return $path;
    }

    public static function writer(?string $code = null): JsonlWriter
    {
        $code ??= self::slideshowCode();
        if (!isset(self::$writers[$code])) {
            $path = self::filePath($code);
            self::$writers[$code] = JsonlWriter::open($path, createDirs: true);
            if (self::debug()) { \fwrite(\STDERR, "[sjl] writer opened: {$path}\n"); }
            \register_shutdown_function(static function () use ($code): void {
                if (isset(self::$writers[$code])) {
                    try { self::$writers[$code]->close(); } catch (\Throwable) {}
                    unset(self::$writers[$code]);
                    if (self::debug()) { \fwrite(\STDERR, "[sjl] writer closed for code={$code}\n"); }
                }
            });
        }
        return self::$writers[$code];
    }

    /**
     * Append one JSON line using JsonlWriter with de-dup token.
     * @param array<string,mixed> $record
     */
    public static function append(array $record, ?string $tokenCode = null, ?string $code = null): void
    {
        $code = self::slideshowCode($code, $record['task_name'] ?? null);

        $record['ts']          ??= (new \DateTimeImmutable('now'))->format('c');
        $record['slideshow']    = $code; // normalized
        $record['working_dir'] ??= ($_ENV['WORKING_DIR'] ?? \getenv('WORKING_DIR') ?: \getcwd());
        $record['castor_file'] ??= self::guessCastorFile($code);

        $tokenCode ??= self::defaultTokenFor($record);

        try {
            self::writer($code)->write($record, (string)$tokenCode);
            if (self::debug()) { \fwrite(\STDERR, "[sjl] wrote token={$tokenCode} record=" . \json_encode($record) . "\n"); }
        } catch (\Throwable $e) {
            if (self::debug()) { \fwrite(\STDERR, "[sjl] write failed: {$e->getMessage()}\n"); }
        }
    }

    private static function defaultTokenFor(array $r): string
    {
        $parts = [
            $r['type']    ?? 'event',
            $r['run_id']  ?? '-',
            \substr((string)($r['task_name'] ?? $r['cmdline'] ?? ''), 0, 60),
            \substr((string)($r['ts'] ?? ''), 0, 25),
        ];
        return \implode('|', $parts);
    }

    public static function genRunId(): string
    {
        $u = \bin2hex(\random_bytes(8));
        $t = (new \DateTimeImmutable('now'))->format('Ymd\THis');
        return $t . '-' . $u;
    }

    /**
     * Prefer env override; else scan included files.
     * If $slideshow is given, prefer filenames containing that code.
     */
    public static function guessCastorFile(?string $slideshow = null): ?string
    {
        // Env overrides (absolute path recommended)
        foreach (['SLIDESHOW_FILE', 'CASTOR_FILE'] as $k) {
            $v = $_ENV[$k] ?? \getenv($k);
            if ($v && @\is_file($v)) {
                if (self::debug()) { \fwrite(\STDERR, "[sjl] file from env {$k}={$v}\n"); }
                return $v;
            }
        }

        // Scan included files
        $files = \get_included_files();
        $candidates = \array_values(\array_filter($files, static fn($f) => \str_ends_with($f, '.castor.php')));
        if (!$candidates) {
            if (self::debug()) { \fwrite(\STDERR, "[sjl] no *.castor.php among included files\n"); }
            return null;
        }

        if ($slideshow) {
            foreach (\array_reverse($candidates) as $f) {
                if (\str_contains(\basename($f), $slideshow)) {
                    if (self::debug()) { \fwrite(\STDERR, "[sjl] file by slideshow match: {$f}\n"); }
                    return $f;
                }
            }
        }

        $last = \end($candidates) ?: null;
        if (self::debug() && $last) { \fwrite(\STDERR, "[sjl] file by last included: {$last}\n"); }
        return $last;
    }
}
