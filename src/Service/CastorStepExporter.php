<?php declare(strict_types=1);
/**
 * file: src/Service/CastorStepExporter.php — unified "slides" naming, slide-level artifacts with /artifacts URLs
 */

namespace Survos\StepBundle\Service;

use Castor\Attribute\AsTask;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use Survos\CoreBundle\Service\SurvosUtils;
use Survos\StepBundle\Metadata\Step;
use Survos\StepBundle\Metadata\Actions\{ ComposerRequire, ImportmapRequire, RequirePackage };
use Symfony\Component\Finder\Finder;

final class CastorStepExporter
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $glob = '*.castor.php',
        private readonly int $depth = 0,
    ) {}

    /**
     * List available castor files as slideshows.
     * @return array<int, array{code:string,path:string}>
     */
    public function listSlideshows(): array
    {
        $out = [];
        foreach ($this->findCastorFiles() as $file) {
            $filename = $file->getFilename(); // e.g. barcode.castor.php
            $out[] = [
                'code' => $this->slugFromFilename($filename),
                'path' => $file->getRealPath() ?: '',
            ];
        }
        return $out;
    }

    /**
     * Export a castor file into a slideshow payload:
     * ['code'=>..., 'path'=>..., 'slides'=>[ ['task_name'=>..., 'title'=>..., 'actions'=>[], 'artifacts'=>[] ], ... ] ]
     * @return array{code:string,path:string,slides: list<array<string,mixed>>}
     */
    public function exportSlides(string $code): array
    {
        $file = $this->resolveFileByCode($code);
        if (!$file) {
            throw new \RuntimeException("Slideshow '{$code}' not found");
        }

        // Include the castor file and detect only the functions it declares, then walk through those.
        $before = get_defined_functions()['user'] ?? [];
        require_once $file;
        $after  = get_defined_functions()['user'] ?? [];
        $newFns = array_values(array_diff($after, $before));

        // Keep only functions declared in THIS file and capture their declaration order
        $declared = [];
        foreach ($newFns as $fnName) {
            $rf = new \ReflectionFunction($fnName);
            if (realpath((string)$rf->getFileName()) === realpath($file)) {
                $declared[] = [
                    'rf'    => $rf,
                    'order' => $rf->getStartLine() ?? PHP_INT_MAX,
                ];
            }
        }

        // Sort by source-order (start line) to preserve "as written" order
        usort($declared, static fn($a, $b) => $a['order'] <=> $b['order']);

        $slides = [];
        foreach ($declared as $entry) {
            /** @var \ReflectionFunction $rf */
            $rf = $entry['rf'];

            $taskAttrs = $rf->getAttributes(AsTask::class, \ReflectionAttribute::IS_INSTANCEOF);
            $stepAttrs = $rf->getAttributes(Step::class,    \ReflectionAttribute::IS_INSTANCEOF);
            if ($taskAttrs === [] || $stepAttrs === []) {
                continue;
            }

            /** @var AsTask $asTask */
            $asTask   = $taskAttrs[0]->newInstance();
            $taskName = $asTask->name ?? $rf->getName();

            /** @var list<Step> $steps */
            $steps = array_map(static fn($a) => $a->newInstance(), $stepAttrs);

            // Append steps in the same order they are declared on the function
            foreach ($steps as $step) {
                $slides[] = [
                    // keep the objects, too little value in serialization
                    'step' => $step,
                    'task' => $asTask,
                    'sparseStep' => SurvosUtils::removeNullsAndEmptyArrays($step),

                    // not sure how much these help!
                    'task_name'   => $taskName,
                    'function'    => $rf->getName(),
                    'title'       => $step->title,
                    'description' => $step->description,
                    'bullets'     => array_values($step->bullets),
                    'website'     => $step->website,
                    'image'       => $step->image,
                    'notes'       => array_values($step->notes),
//                    'actions'     => $this->serializeActions($step->actions, $taskName, $step->title),
                    'artifacts'   => $this->findSlideArtifacts($taskName, $step->title),
                ];
            }
        }

        return [
            'code'   => $code,
            'path'   => $file,
            'slides' => $slides, // already in declaration order
        ];
    }

    /** Discover ALL files under /public/artifacts/<safeTask>/<safeStep> (recursive). */
    private function findSlideArtifacts(string $task, string $stepTitle): array
    {
        $root     = rtrim($this->projectDir, '/');
        $pubRoot  = $root . '/public';
        $safeTask = $this->safe($task);
        $safeStep = $this->safe($stepTitle);
        $dir      = "{$pubRoot}/artifacts/{$safeTask}/{$safeStep}";

        if (!is_dir($dir)) {
            return [];
        }

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($rii as $f) {
            /** @var \SplFileInfo $f */
            if (!$f->isFile()) continue;

            $abs = $f->getPathname();
            $url = substr($abs, strlen($pubRoot)); // map FS path to public URL; drops /public
            $url = str_replace(DIRECTORY_SEPARATOR, '/', $url);
            if (!str_starts_with($url, '/')) { $url = '/' . ltrim($url, '/'); }

            $files[] = [
                'path'     => $url,                       // /artifacts/...
                'name'     => $f->getFilename(),
                'size'     => $f->getSize(),
                'mtime'    => date('c', $f->getMTime()),
                // optional: include contents for debug view
                'contents' => @file_get_contents($abs) ?: null,
            ];
        }
        return $files;
    }

    /**
     * @todo: archive slideshows as JSON.  Until then, just use the objects, much easier.
     *
     * Flatten actions for JSON. We do NOT attach per-action artifacts here;
     * the slide-level artifacts (recursive) already include /files/<actionKey>/… content.
     * @param list<object> $actions
     * @return list<array<string,mixed>>
     */
    private function serializeActions(array $actions, string $taskName, string $stepTitle): array
    {
        $out = [];
        $i   = 0;

        foreach ($actions as $a) {
            $i++;
            $rc   = new ReflectionClass($a);
            $type = $rc->getShortName();
            $row  = ['type' => $type];

            if ($a instanceof ComposerRequire || $a instanceof ImportmapRequire) {
                $row['cwd']  = $a->cwd ?? null;
                $row['note'] = $a->note ?? null;
                $row['dev']  = $a instanceof ComposerRequire ? (bool)$a->dev : null;

                $reqs = [];
                foreach ($a->requires as $req) {
                    if ($req instanceof RequirePackage) {
                        $reqs[] = ['package'=>$req->package,'comment'=>$req->comment,'constraint'=>$req->constraint];
                    } elseif (is_array($req)) {
                        $reqs[] = [
                            'package'=> $req['package'] ?? (string)($req[0] ?? ''),
                            'comment'=> $req['comment'] ?? null,
                            'constraint'=> $req['constraint'] ?? null,
                        ];
                    } else {
                        $reqs[] = ['package'=>(string)$req,'comment'=>null,'constraint'=>null];
                    }
                }
                $row['requires']  = $reqs;
                $row['multiline'] = $a->asMultilineCommand();
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
            if (property_exists($a,'note') && !isset($row['note'])) $row['note'] = $a->note;
            if (property_exists($a,'cwd')  && !isset($row['cwd']))  $row['cwd']  = $a->cwd;

            $out[] = $row;
        }

        return $out;
    }

    /** @return \Symfony\Component\Finder\SplFileInfo[] */
    private function findCastorFiles(): array
    {
        $finder = (new Finder())
            ->files()
            ->in($this->projectDir . '/castor')
            ->name($this->groB ?? '*.castor.php')
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
        $base = pathinfo($filename, PATHINFO_FILENAME);        // e.g. barcode.castor
        $base = preg_replace('/\.castor$/i', '', $base) ?? $base;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $base) ?? $base;
        return strtolower(trim($slug, '-'));
    }

    private function safe(string $s): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $s) ?: 'x';
    }
}
