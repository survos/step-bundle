<?php declare(strict_types=1);

// file: src/Castor/StepHelpers.php  (or wherever you keep it)
use Survos\StepBundle\Metadata\Step;

/**
 * Extract action objects from the CURRENT task function's #[Step(...)] attributes.
 * Works when called *inside* a task function (e.g., barcode_install()).
 *
 * @return array<int,object>
 */
function _actions_from_current_task(): array
{
    // Walk the call stack to find the first user frame (function or method)
    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

    $ref = null;
    foreach ($bt as $frame) {
        // Prefer the first frame that is NOT this helper
        if (($frame['function'] ?? null) === __FUNCTION__) {
            continue;
        }

        // Method task (class + method)
        if (!empty($frame['class']) && !empty($frame['function'])) {
            try {
                $ref = new ReflectionMethod($frame['class'], $frame['function']);
                break;
            } catch (\ReflectionException) {
                // keep looking
            }
        }

        // Plain function task
        if (!empty($frame['function']) && empty($frame['class'])) {
            try {
                $ref = new ReflectionFunction($frame['function']);
                break;
            } catch (\ReflectionException) {
                // keep looking
            }
        }
    }

    if (!$ref) {
        // Could not determine the caller; return empty rather than throwing
        return [];
    }

    // Collect #[Step] attributes (including subclasses)
    $attrs = $ref->getAttributes(Step::class, ReflectionAttribute::IS_INSTANCEOF);

    $actions = [];
    foreach ($attrs as $attr) {
        /** @var Step $step */
        $step = $attr->newInstance();
        foreach ($step->actions as $a) {
            $actions[] = $a;
        }
    }

    return $actions;
}
