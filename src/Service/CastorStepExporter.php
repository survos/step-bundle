<?php declare(strict_types=1);
// file: src/Service/CastorStepExporter.php — robust slide/action artifact lookup (safe + fuzzy)

namespace Survos\StepBundle\Service;

use Castor\Attribute\AsTask;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use Survos\StepBundle\Metadata\Step;
use Survos\StepBundle\Metadata\Actions\{
    ComposerRequire, ImportmapRequire, RequirePackage
};
use Symfony\Component\Finder\Finder;

final class CastorStepExporter
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $glob = '*.castor.php',
        private readonly int    $depth = 0,
    ) {}

    public function listSlideshows(): array
    {
        $files = $this->findCastorFiles();
        $out = [];
        foreach ($files as $file) {
            $out[] = [
                'code' => $this->slugFromFilename($file->getFilename()),
                'path' => $file->getRealPath() ?: '',
            ];
        }
        return $out;
    }

    /** Export a given castor file (by {code}) into a slideshow-ready structure. */
    public function exportSlides(string $code): array
    {
        $file = $this->resolveFileByCode($code);
        if (!$file) {
            throw new \RuntimeException("Slideshow '{$code}' not found");
        }

        $before = get_defined_functions()['user'] ?? [];
        require_once $file;
        $after  = get_defined_functions()['user'] ?? [];
        $newFns = array_values(array_diff($after, $before));

        $slides = [];
        foreach ($newFns as $fnName) {
            $rf = new ReflectionFunction($fnName);
            $taskAttrs = $rf->getAttributes(AsTask::class, ReflectionAttribute::IS_INSTANCEOF);
            $stepAttrs = $rf->getAttributes(Step::class,    ReflectionAttribute::IS_INSTANCEOF);
            if ($taskAttrs === [] || $stepAttrs === []) {
                continue;
            }

            /** @var AsTask $asTask */
            $asTask  = $taskAttrs[0]->newInstance();
            $taskName = $asTask->name ?? $rf->getName();
            $steps    = array_map(static fn($a) => $a->newInstance(), $stepAttrs);

            foreach ($steps as $step) {
                $slides[] = [
                    'task_name'   => $taskName,
                    'function'    => $rf->getName(),
                    'title'       => $step->title,
                    'description' => $step->description,
                    'bullets'     => array_values($step->bullets),
                    'website'     => $step->website,
                    'image'       => $step->image,
                    'notes'       => array_values($step->notes),

                    // actions carry per-action artifacts
                    'actions'     => $this->serializeActions($step->actions, $taskName, $step->title),

                    // ALWAYS set slide-level artifacts (may be empty)
                    'artifacts'   => $this->findSlideArtifacts($taskName, $step->title),
                ];
            }
        }

        usort($slides, static function (array $a, array $b) {
            $na = $a['task_name']; $nb = $b['task_name'];
            $pa = preg_match('/^\d+/', $na, $ma) ? (int)$ma[0] : PHP_INT_MAX;
            $pb = preg_match('/^\d+/', $nb, $mb) ? (int)$mb[0] : PHP_INT_MAX;
            return $pa <=> $pb ?: strcmp($na, $nb);
        });

        return [
            'code'   => $code,
            'path'   => $file,
            'slides' => $slides,
        ];
    }

    // ---------- artifacts (robust) ----------

    private function findSlideArtifacts(string $task, string $stepTitle): array
    {
        $root = $this->projectDir . '/public/artifacts';
        $safeTask = $this->safe($task);
        $safeStep = $this->safe($stepTitle);
        $dir = "{$root}/{$safeTask}/{$safeStep}";

        if (!is_dir($dir)) {
            return [];
        }

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($rii as $f) {
            if (!$f->isFile()) continue;
            $path = substr($f->getPathname(), strlen($this->projectDir)); // keep /public prefix
            $files[] = [
                'path'  => str_replace(DIRECTORY_SEPARATOR, '/', $path),
                'name'  => $f->getFilename(),
                'size'  => $f->getSize(),
                'mtime' => date('c', $f->getMTime()),
                'contents' => @file_get_contents($f->getPathname()), // 👈 load content for rendering
            ];
        }

        return $files;
    }


    /**
     * Resolve the actual slide directory on disk for (task, stepTitle),
     * trying both the direct "safe" path and a fuzzy match.
     */
    private function resolveSlideDir(string $task, string $stepTitle): ?string
    {
        $root = $this->projectDir . '/public/artifacts';
        $safeTask = $this->safe($task);
        $safeStep = $this->safe($stepTitle);

        // 1) direct path
        $dir = "{$root}/{$safeTask}/{$safeStep}";
        if (is_dir($dir)) {
            return $dir;
        }

        // 2) fuzzy: scan task dir and compare canonical names (strip non-alnum)
        $taskDir = "{$root}/{$safeTask}";
        if (!is_dir($taskDir)) {
            return null;
        }

        $want = $this->canon($stepTitle);
        foreach (scandir($taskDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $full = $taskDir . '/' . $entry;
            if (!is_dir($full)) continue;
            if ($this->canon($entry) === $want) {
                return $full;
            }
        }

        return null;
    }

    /** Recursive scanner that returns File[] with public paths. */
    private function scanFilesRecursively(string $absDir): array
    {
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($rii as $f) {
            /** @var \SplFileInfo $f */
            if (!$f->isFile()) continue;
            $rel = substr($f->getPathname(), strlen($this->projectDir));
            $files[] = [
                'path'  => str_replace(DIRECTORY_SEPARATOR, '/', $rel),
                'name'  => $f->getFilename(),
                'size'  => $f->getSize(),
                'mtime' => date('c', $f->getMTime()),
            ];
        }

        return $files;
    }

    // ---------- actions ----------

    /** @param list<object> $actions @return list<array<string,mixed>> */
    private function serializeActions(array $actions, string $taskName, string $stepTitle): array
    {
        $out = [];
        $i = 0;

        foreach ($actions as $a) {
            $i++;
            $rc   = new ReflectionClass($a);
            $type = $rc->getShortName();
            $row  = ['type' => $type];

            $actionKey = $this->actionKeyFor($a, $i);

            if ($a instanceof ComposerRequire || $a instanceof ImportmapRequire) {
                $row['cwd']   = $a->cwd ?? null;
                $row['note']  = $a->note ?? null;
                $row['dev']   = $a instanceof ComposerRequire ? (bool)$a->dev : null;

                $reqs = [];
                foreach ($a->requires as $req) {
                    if ($req instanceof RequirePackage) {
                        $reqs[] = [
                            'package'    => $req->package,
                            'comment'    => $req->comment,
                            'constraint' => $req->constraint,
                        ];
                    } elseif (is_array($req)) {
                        $reqs[] = [
                            'package'    => $req['package']    ?? (string)($req[0] ?? ''),
                            'comment'    => $req['comment']    ?? null,
                            'constraint' => $req['constraint'] ?? null,
                        ];
                    } else {
                        $reqs[] = ['package' => (string)$req, 'comment' => null, 'constraint' => null];
                    }
                }
                $row['requires']   = $reqs;
                $row['multiline']  = $a->asMultilineCommand();
                $row['artifacts']  = [];
                $out[] = $row;
                continue;
            }

            foreach (get_object_vars($a) as $prop => $val) {
                if ($val instanceof \Closure) { $row[$prop] = '[closure]'; continue; }
                if (\is_scalar($val) || $val === null) {
                    $row[$prop] = $val;
                } elseif (\is_array($val)) {
                    $row[$prop] = array_map(static fn($v) => \is_scalar($v) || $v === null ? $v : '[object]', $val);
                } else {
                    $row[$prop] = '[object]';
                }
            }
            if (property_exists($a, 'note') && !isset($row['note'])) { $row['note'] = $a->note; }
            if (property_exists($a, 'cwd')  && !isset($row['cwd']))  { $row['cwd']  = $a->cwd;  }

            $row['artifacts'] = [];
            $out[] = $row;
        }

        return $out;
    }

    /** Compute a RunStep-compatible actionKey. */
    private function actionKeyFor(object $action, int $index): string
    {
        $short = (new ReflectionClass($action))->getShortName();
        $base  = strtolower($short);
        $id    = property_exists($action, 'id') ? (string)($action->id ?? '') : '';
        if ($id !== '') {
            // preserve writer’s case, but normalize punctuation
            return $base . '-' . $this->safe($id);
        }
        return sprintf('%s-%02d', $base, $index);
    }

    // ---------- file discovery / names ----------

    /** @return \Symfony\Component\Finder\SplFileInfo[] */
    private function findCastorFiles(): array
    {
        $root = $this->projectDir . '/castor';

        $finder = new Finder();
        $finder->files()
            ->in($root)
            ->name($this->glob)
            ->depth('== ' . $this->depth);

        return iterator_to_array($finder, false);
    }

    private function resolveFileByCode(string $code): ?string
    {
        foreach ($this->findCastorFiles() as $file) {
            if ($this->slugFromFilename($file->getFilename()) === $code) {
                return $file->getRealPath() ?: null;
            }
        }
        return null;
    }

    private function slugFromFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/\.castor$/i', '', $base) ?? $base;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $base) ?? $base;
        return strtolower(trim($slug, '-'));
    }

    private function safe(string $s): string
    {
        // same contract as writer: any non [A-Za-z0-9._-] becomes a single dash
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $s) ?: 'x';
    }

    private function canon(string $s): string
    {
        // for fuzzy match: strip everything but alnum, lowercase
        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $s) ?? '');
    }
}
