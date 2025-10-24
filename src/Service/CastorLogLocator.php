<?php declare(strict_types=1);

namespace Survos\StepBundle\Service;

final class CastorLogLocator
{
    public function __construct(private readonly string $projectDir)
    {
    }

    public function logDir(): string
    {
        $dir = $_ENV['SLIDESHOW_OUT_DIR'] ?? getenv('SLIDESHOW_OUT_DIR')
            ?: $_ENV['CASTOR_LOG_DIR'] ?? getenv('CASTOR_LOG_DIR')
            ?: $this->projectDir . '/.castor/slideshows';

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /** @return array<int,array{code:string,path:string,size:int,mtime:int}> */
    public function listLogs(): array
    {
        $dir = $this->logDir();
        $rows = [];
        foreach (glob($dir . '/*.jsonl') ?: [] as $path) {
            $code = basename($path, '.jsonl');
            $rows[] = [
                'code'  => $code,
                'path'  => $path,
                'size'  => @filesize($path) ?: 0,
                'mtime' => @filemtime($path) ?: 0,
            ];
        }
        usort($rows, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $rows;
    }

    public function pathFor(string $code): string
    {
        return $this->logDir() . '/' . $code . '.jsonl';
    }

    /** Tail N lines efficiently without loading whole file. */
    public function tail(string $file, int $lines = 300): array
    {
        $f = @fopen($file, 'rb');
        if (!$f) { return []; }

        $buffer = '';
        $chunkSize = 4096;
        $pos = -1;
        $lineCount = 0;

        fseek($f, 0, SEEK_END);
        $fileSize = ftell($f);

        while ($lineCount <= $lines && -$pos < $fileSize) {
            $pos -= $chunkSize;
            if (fseek($f, max(0, $pos), SEEK_END) !== 0) {
                fseek($f, 0);
            }
            $read = fread($f, $chunkSize);
            if ($read === false) { break; }
            $buffer = $read . $buffer;
            $lineCount = substr_count($buffer, "\n");
            if (ftell($f) === 0) { break; }
        }
        fclose($f);

        $arr = explode("\n", trim($buffer));
        $arr = array_slice($arr, -$lines);
        return $arr;
    }
}
