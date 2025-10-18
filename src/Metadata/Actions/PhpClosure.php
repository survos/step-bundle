<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Closure;
use Survos\StepBundle\Metadata\Action;

/** Invoke arbitrary PHP at execution time (use sparingly). */
final class PhpClosure extends Action
{
    public function __construct(
        public Closure $fn,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
    }
}
