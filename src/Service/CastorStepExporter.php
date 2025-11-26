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
use Survos\StepBundle\Action\{
    ComposerRequire,
    ImportmapRequire,
    RequirePackage,
    SplitSlide
};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RequestStack;

final class CastorStepExporter
{
    public const ARTIFACT_ROOT = 'public/artifacts/';

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private ?string $projectDir = null,
        private readonly string $glob = '*.castor.php',
        private readonly int $depth = 0,
        private ?RequestStack $requestStack = null,
        private ?ArtifactLocator $artifactLocator = null,
    ) {
    }

    public function artifactsDir(string $projectName): string
    {
        return self::ARTIFACT_ROOT . $projectName . '/';
    }

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
     * @return array{code:string,path:string,slides:list<array<string,mixed>>}
     */
    public function exportSlides(?string $code = null, ?string $file = null): array
    {
        $file = $file ?? $this->resolveFileByCode($code);
        if (!$file) {
            throw new \RuntimeException("Slideshow '{$code}' $file not found");
        }

        // Include the castor file and detect only the functions it declares, then walk through those.
        $before = get_defined_functions()['user'] ?? [];
        require_once $file;
        $after  = get_defined_functions()['user'] ?? [];
        $newFns = array_values(array_diff($after, $before));

        // Keep only functions declared in THIS file and capture their declaration order
        $declared = [];
        foreach ($newFns as $fnName) {
            $rf = new ReflectionFunction($fnName);
            if (realpath((string) $rf->getFileName()) === realpath($file)) {
                $declared[] = [
                    'rf'    => $rf,
                    'order' => $rf->getStartLine() ?? \PHP_INT_MAX,
                ];
            }
        }

        // Sort by source-order (start line) to preserve "as written" order
        usort($declared, static fn(array $a, array $b) => $a['order'] <=> $b['order']);

        $slides = [];
        foreach ($declared as $entry) {
            /** @var ReflectionFunction $rf */
            $rf = $entry['rf'];

            $taskAttrs = $rf->getAttributes(AsTask::class, ReflectionAttribute::IS_INSTANCEOF);
            $stepAttrs = $rf->getAttributes(Step::class,    ReflectionAttribute::IS_INSTANCEOF);
            if ($taskAttrs === [] || $stepAttrs === []) {
                continue;
            }

            /** @var AsTask $asTask */
            $asTask   = $taskAttrs[0]->newInstance();
            $taskName = $asTask->name ?? $rf->getName();

            /** @var list<Step> $steps */
            $steps = array_map(static fn(ReflectionAttribute $a) => $a->newInstance(), $stepAttrs);

            // Append steps in the same order they are declared on the function
            foreach ($steps as $step) {
                $project = $code ?? $this->requestStack?->getCurrentRequest()?->attributes->get('code');

                /** @var list<array<int,object>> $chunks */
                $chunks = [];
                /** @var list<array<int,array<string,mixed>>> $chunkArtifacts */
                $chunkArtifacts = [];
                $currentChunkIndex = 0;
                $chunks[$currentChunkIndex] = [];
                $chunkArtifacts[$currentChunkIndex] = [];

                foreach ($step->actions as $action) {
                    // SplitSlide acts as a control marker: start a new chunk/slide
                    if ($action instanceof SplitSlide) {
                        if ($chunks[$currentChunkIndex] !== []) {
                            $currentChunkIndex++;
                            $chunks[$currentChunkIndex] = [];
                            $chunkArtifacts[$currentChunkIndex] = [];
                        }
                        continue;
                    }

                    // Normal action: enrich + attach artifacts
                    $action->project         = $project;
                    $action->artifactLocator = $this->artifactLocator;

                    $chunks[$currentChunkIndex][] = $action;

                    if (!empty($action->artifactId)) {
                        $artifactName = $action->a ?? $action->artifactId;

                        $content = $this->artifactLocator?->read($action->project, $artifactName);
                        $action->artifact = $content;

                        $chunkArtifacts[$currentChunkIndex][] = [
                            'name'     => $artifactName,
                            'mtime'    => null, // @todo
                            'size'     => $content ? \strlen($content) : 0,
                            'path'     => $this->artifactLocator?->absolute($action->project, $artifactName),
                            'contents' => $content,
                        ];
                    }
                }

                // If we never added any real actions (only SplitSlide or nothing),
                // produce a single slide with the original step and no actions/artifacts.
                $allEmpty = true;
                foreach ($chunks as $chunk) {
                    if ($chunk !== []) {
                        $allEmpty = false;
                        break;
                    }
                }

                if ($allEmpty) {
                    $step->actions = [];
                    $slides[] = [
                        'step'       => $step,
                        'actions'    => [],
                        'task'       => $asTask,
                        'sparseStep' => SurvosUtils::removeNullsAndEmptyArrays($step),

                        'task_name'   => $taskName,
                        'function'    => $rf->getName(),
                        'title'       => $step->title,
                        'description' => $step->description,
                        'bullets'     => \array_values($step->bullets),
                        'website'     => $step->website,
                        'image'       => $step->image,
                        'notes'       => \array_values($step->notes),
                        'artifacts'   => [],
                    ];
                    continue;
                }

                // Create one slide per non-empty chunk (sub-step)
                foreach ($chunks as $chunkIndex => $chunkActions) {
                    if ($chunkActions === []) {
                        continue;
                    }

                    // Clone the Step so each slide has its own actions collection
                    $subStep = clone $step;
                    $subStep->actions = $chunkActions;

                    $slides[] = [
                        'step'       => $subStep,
                        'actions'    => $chunkActions,
                        'task'       => $asTask,
                        'sparseStep' => SurvosUtils::removeNullsAndEmptyArrays($subStep),

                        'task_name'   => $taskName,
                        'function'    => $rf->getName(),
                        'title'       => $step->title,
                        'description' => $step->description,
                        'bullets'     => \array_values($step->bullets),
                        'website'     => $step->website,
                        'image'       => $step->image,
                        'notes'       => \array_values($step->notes),
                        'artifacts'   => $chunkArtifacts[$chunkIndex] ?? [],
                    ];
                }
            }
        }

        return [
            'code'   => $code,
            'path'   => $file,
            'slides' => $slides, // already in declaration order (and split by SplitSlide)
        ];
    }

    /** Discover ALL files under /public/artifacts/<safeTask>/<safeStep> (recursive). */
    private function findSlideArtifacts(string $task, string $stepTitle): array
    {
        $root     = rtrim($this->projectDir ?? '', '/');
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
            if (!$f->isFile()) {
                continue;
            }

            $abs = $f->getPathname();
            $url = substr($abs, \strlen($pubRoot)); // map FS path to public URL; drops /public
            $url = str_replace(\DIRECTORY_SEPARATOR, '/', $url);
            if (!str_starts_with($url, '/')) {
                $url = '/' . ltrim($url, '/');
            }

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
                $row['dev']  = $a instanceof ComposerRequire ? (bool) $a->dev : null;

                $reqs = [];
                foreach ($a->requires as $req) {
                    if ($req instanceof RequirePackage) {
                        $reqs[] = [
                            'package'    => $req->package,
                            'comment'    => $req->comment,
                            'constraint' => $req->constraint,
                        ];
                    } elseif (\is_array($req)) {
                        $reqs[] = [
                            'package'    => $req['package'] ?? (string) ($req[0] ?? ''),
                            'comment'    => $req['comment'] ?? null,
                            'constraint' => $req['constraint'] ?? null,
                        ];
                    } else {
                        $reqs[] = [
                            'package'    => (string) $req,
                            'comment'    => null,
                            'constraint' => null,
                        ];
                    }
                }
                $row['requires']  = $reqs;
                $row['multiline'] = $a->asMultilineCommand();
                $out[] = $row;
                continue;
            }

            foreach (get_object_vars($a) as $prop => $val) {
                if ($val instanceof \Closure) {
                    $row[$prop] = '[closure]';
                    continue;
                }
                if (\is_scalar($val) || $val === null) {
                    $row[$prop] = $val;
                } elseif (\is_array($val)) {
                    $row[$prop] = array_map(
                        static fn($v) => \is_scalar($v) || $v === null ? $v : '[object]',
                        $val
                    );
                } else {
                    $row[$prop] = '[object]';
                }
            }
            if (property_exists($a, 'note') && !isset($row['note'])) {
                $row['note'] = $a->note;
            }
            if (property_exists($a, 'cwd') && !isset($row['cwd'])) {
                $row['cwd'] = $a->cwd;
            }

            $out[] = $row;
        }

        return $out;
    }

    /** @return \Symfony\Component\Finder\SplFileInfo[] */
    private function findCastorFiles(): array
    {
        $finder = (new Finder())
            ->files()
            ->in(($this->projectDir ?? '') . '/conference')
            ->name($this->glob ?? '*.castor.php')
            ->depth('== ' . $this->depth)
            ->sortByName()
        ;

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
        $base = pathinfo($filename, \PATHINFO_FILENAME);        // e.g. barcode.castor
        $base = preg_replace('/\.castor$/i', '', $base) ?? $base;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $base) ?? $base;

        return strtolower(trim($slug, '-'));
    }

    private function safe(string $s): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $s) ?: 'x';
    }
}
