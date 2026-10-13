<?php

declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use Survos\StepBundle\Metadata\Action;

/** Declarative artifact to snapshot a file after an action runs. */
final class SplitSlide extends AbstractAction
{
    public function __construct(
        public ?string $nextDescription = 'extended step', // li?
    ) {
    }
    public ?bool $noop = true;

    public function summary(): string
    {
        return 'force new slide '.__METHOD__;
        // TODO: Implement summary() method.
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        // no-op
    }

    public function viewTemplate(): string
    {
        return 'break.html.twig';
    }
}
