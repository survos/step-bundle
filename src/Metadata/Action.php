<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata;

use ReflectionClass;

/**
 * Base Action (data-only; immutable-like DTO).
 * Executors/renderers interpret these records later.
 */
abstract class Action
{
    public function __construct(
        public ?string $note = null,
        public ?string $cwd  = null, // working directory hint for executors
    ) {}

    /** Machine-friendly type, useful for JSON/exporters. */
    public string $type { get => (new ReflectionClass($this))->getShortName(); }
}
