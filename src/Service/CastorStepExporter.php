<?php declare(strict_types=1);

// src/Service/CastorStepExporter.php

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
        private readonly int $depth = 0,
    ) {}

    /**
     * Lists available castor files as slideshows.
     * NOTE: codes are URL-safe slugs (no dots), e.g. "basic.demo.castor.php" → "basic-demo"
     *
     * @return array<int, array{code:string, path:string}>
     */
    public function listSlideshows(): array
    {
        $files = $this->findCastorFiles();
        $out = [];
        foreach ($files as $file) {
            $filename = $file->getFilename(); // e.g. basic.demo.castor.php
            $out[] = [
                'code' => $this->slugFromFilename($filename),   // e.g. basic-demo
                'path' => $file->getRealPath() ?: '',
            ];
        }
        return $out;
    }

    /**
     * Export a given castor file (by {code}) into a slideshow-ready deck structure.
     */
    public function exportSteps(string $code): array
    {
        $file = $this->resolveFileByCode($code);
        if (!$file) {
            throw new \RuntimeException("Slideshow '{$code}' not found");
        }

        $before = get_defined_functions()['user'] ?? [];
        require_once $file;
        $after  = get_defined_functions()['user'] ?? [];
        $newFns = array_values(array_diff($after, $before));

        $tasks = [];
        foreach ($newFns as $fnName) {
            $rf = new ReflectionFunction($fnName);
            $taskAttrs = $rf->getAttributes(AsTask::class, ReflectionAttribute::IS_INSTANCEOF);
            $stepAttrs = $rf->getAttributes(Step::class,    ReflectionAttribute::IS_INSTANCEOF);
            if ($taskAttrs === [] || $stepAttrs === []) {
                continue;
            }
            /** @var AsTask $asTask */
            $asTask = $taskAttrs[0]->newInstance();
            /** @var Step $step */
            $step = $stepAttrs[0]->newInstance();

            $tasks[] = [
                'name'        => $asTask->name ?? $rf->getName(),
                'function'    => $rf->getName(),
                'title'       => $step->title,
                'description' => $step->description,
                'bullets'     => array_values($step->bullets),
                'website'     => $step->website,
                'image'       => $step->image,
                'notes'       => array_values($step->notes),
                'actions'     => $this->serializeActions($step->actions),
            ];
        }

        // Sort: numeric prefix first, then alpha
        usort($tasks, static function ($a, $b) {
            $na = $a['name']; $nb = $b['name'];
            $pa = preg_match('/^\d+/', $na, $ma) ? (int)$ma[0] : PHP_INT_MAX;
            $pb = preg_match('/^\d+/', $nb, $mb) ? (int)$mb[0] : PHP_INT_MAX;
            return $pa <=> $pb ?: strcmp($na, $nb);
        });

        return [
            'code'  => $code,
            'path'  => $file,
            'tasks' => $tasks,
        ];
    }

    /** @return \Symfony\Component\Finder\SplFileInfo[] */
    private function findCastorFiles(): array
    {
        // Search one level above the Symfony app (as before)
        $root = $this->projectDir;

        $finder = new Finder();
        $finder->files()
            ->in($root)
            ->name($this->glob)
            ->depth('== ' . $this->depth);

        return iterator_to_array($finder, false);
    }

    /**
     * Map a clean URL code back to an actual *.castor.php file.
     * Accepts codes without dots: "basic-demo" will match "basic.demo.castor.php".
     */
    private function resolveFileByCode(string $code): ?string
    {
        foreach ($this->findCastorFiles() as $file) {
            if ($this->slugFromFilename($file->getFilename()) === $code) {
                return $file->getRealPath() ?: null;
            }
        }
        return null;
    }

    /**
     * Convert filename -> URL-safe code.
     * Rules:
     *   1) strip final ".php"
     *   2) strip trailing ".castor" token if present
     *   3) replace any non-alnum with "-"
     *   4) trim extra dashes, lowercase
     *
     * Examples:
     *   "basic.demo.castor.php" -> "basic-demo"
     *   "demo.castor.php"       -> "demo"
     *   "my_demo.castor.php"    -> "my-demo"
     */
    private function slugFromFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);        // e.g. basic.demo.castor
        $base = preg_replace('/\.castor$/i', '', $base) ?? $base; // e.g. basic.demo
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $base) ?? $base;
        $slug = trim($slug, '-');
        return strtolower($slug);
    }

    /**
     * Flatten action objects to JSON-ready arrays, with special handling for
     * ComposerRequire / ImportmapRequire so we preserve "requires" AND a
     * pre-rendered "multiline" command the renderer can print directly.
     *
     * @param list<object> $actions
     * @return list<array<string,mixed>>
     */
    private function serializeActions(array $actions): array
    {
        $out = [];

        foreach ($actions as $a) {
            $rc   = new ReflectionClass($a);
            $type = $rc->getShortName();
            $row  = ['type' => $type];

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
                $row['requires']  = $reqs;
                $row['multiline'] = $a->asMultilineCommand();
                $out[] = $row;
                continue;
            }

            // Generic scalar/array-only snapshot
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

            $out[] = $row;
        }

        return $out;
    }
}
