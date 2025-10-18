<?php declare(strict_types=1);

namespace Survos\StepBundle\Service;

use Castor\Attribute\AsTask;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use Survos\StepBundle\Metadata\Step;
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
     *
     * @return array<int, array{code:string, path:string}>
     */
    public function listSlideshows(): array
    {
        $files = $this->findCastorFiles();
        $out = [];
        foreach ($files as $file) {
            $out[] = [
                'code' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
                'path' => $file->getRealPath() ?: '',
            ];
        }
        return $out;
    }

    /**
     * Export a given castor file (by {code}) into a slideshow-ready deck structure.
     *
     * @return array{
     *   code:string,
     *   path:string,
     *   tasks: list<array{
     *     name:string,
     *     function:string,
     *     title:string,
     *     description:string,
     *     bullets:list<string>,
     *     website:?string,
     *     image:?string,
     *     notes:list<string>,
     *     actions:list<array{
     *       type:string,
     *       note:?string,
     *       cwd:?string
     *     }>
     *   }>
     * }
     */
    public function exportSteps(string $code): array
    {
        $file = $this->findCastorFileByCode($code);
        if (!$file) {
            throw new \RuntimeException("Slideshow '{$code}' not found");
        }

        // Capture user-defined functions before/after we require the castor file.
        $before = get_defined_functions()['user'] ?? [];
        require_once $file;
        $after = get_defined_functions()['user'] ?? [];

        $newFns = array_values(array_diff($after, $before));
        $tasks = [];

        foreach ($newFns as $fnName) {
            $rf = new ReflectionFunction($fnName);

            // Only include functions that have both #[AsTask] and #[Step]
            $taskAttrs = $rf->getAttributes(AsTask::class, ReflectionAttribute::IS_INSTANCEOF);
            $stepAttrs = $rf->getAttributes(Step::class, ReflectionAttribute::IS_INSTANCEOF);

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

        // Sort tasks by numeric prefix if present (e.g., "0-...", "1-...")
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
        // Heuristic: search one level above the Symfony app (like your draft).
        $root = \dirname($this->projectDir);

        $finder = new Finder();
        $finder->files()
            ->in($root)
            ->name($this->glob)
            ->depth('== ' . $this->depth);

        return iterator_to_array($finder, false);
    }

    private function findCastorFileByCode(string $code): ?string
    {
        $root = \dirname($this->projectDir);
        $path = $root . '/' . $code . '.castor.php';

        return \is_file($path) ? $path : null;
    }

    /**
     * Flatten action objects to simple arrays (safe to JSON-encode).
     * For closures or complex objects, we emit a hint rather than raw data.
     *
     * @param list<object> $actions
     * @return list<array<string,mixed>>
     */
    private function serializeActions(array $actions): array
    {
        $out = [];

        foreach ($actions as $a) {
            $type = (new ReflectionClass($a))->getShortName();
            $row  = ['type' => $type];

            foreach (get_object_vars($a) as $prop => $val) {
                // Hide closures and non-serializable bits
                if ($val instanceof \Closure) {
                    $row[$prop] = '[closure]';
                    continue;
                }
                // Simple scalars / arrays only
                if (\is_scalar($val) || \is_null($val)) {
                    $row[$prop] = $val;
                } elseif (\is_array($val)) {
                    // keep only scalars in arrays for now
                    $row[$prop] = array_map(static fn($v) => \is_scalar($v) || $v === null ? $v : '[object]', $val);
                } else {
                    $row[$prop] = '[object]';
                }
            }

            // Common base props (note, cwd) live on parent; include if present.
            if (property_exists($a, 'note')) { $row['note'] = $a->note; }
            if (property_exists($a, 'cwd'))  { $row['cwd']  = $a->cwd;  }

            $out[] = $row;
        }

        return $out;
    }
}
