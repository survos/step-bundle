<?php
// src/Util/StepReflector.php
declare(strict_types=1);

namespace Survos\StepBundle\Util;

use Castor\Attribute\AsTask;
use Survos\StepBundle\Metadata\Step;
use ReflectionFunction;

final class StepReflector
{
    /**
     * @return list<array{ task?: array, function: string, steps: list<array> }>
     */
    public static function parseCastorFile(string $filepath): array
    {
        if (!is_file($filepath)) {
            throw new \RuntimeException("Castor file not found: $filepath");
        }

        // snapshot existing functions
        $before = get_defined_functions()['user'];

        // load the file (idempotent if already loaded)
        require_once $filepath;

        $after = get_defined_functions()['user'];
        $newFunctions = array_diff($after, $before);

        $out = [];
        foreach ($newFunctions as $fn) {
            $rf = new ReflectionFunction($fn);
            if ($rf->getFileName() !== realpath($filepath)) {
                continue; // ignore functions from other files pulled in by require
            }

            $taskAttrs = $rf->getAttributes(AsTask::class);
            $stepAttrs = $rf->getAttributes(Step::class);

            if (!$stepAttrs) {
                continue; // we only care about functions that define Step(s)
            }

            $task = null;
            if ($taskAttrs) {
                /** @var AsTask $taskInst */
                $taskInst = $taskAttrs[0]->newInstance();
                $task = [
                    'name'        => $taskInst->name ?? $rf->getName(),
                    'description' => $taskInst->description ?? '',
                ];
            }

            $steps = [];
            foreach ($stepAttrs as $a) {
                /** @var Step $stepInst */
                $stepInst = $a->newInstance();
                $steps[] = [
                    'title'       => $stepInst->title,
                    'bullets'     => $stepInst->bullets,
                    'actions'     => $stepInst->actions,
                    'website'     => $stepInst->website ?? null,
                    'note'        => $stepInst->note ?? null,
                ];
            }

            $out[] = [
                'task'     => $task,
                'function' => $rf->getName(),
                'steps'    => $steps,
            ];
        }

        return $out;
    }
}
