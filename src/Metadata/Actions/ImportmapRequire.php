<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/**
 * Aggregate "console importmap:require" packages with comments.
 *
 * Example:
 *   new ImportmapRequire([
 *       new Require('@tabler/core', 'Tabler UI'),
 *       new Require('@tabler/icons', 'Icons'),
 *   ], note: 'Install front-end libs', cwd: 'demo')
 */
final class ImportmapRequire extends Action
{
    /** @var list<Require> */
    public array $requires;

    public function __construct(
        array $requires,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->requires = $requires;
    }

    /** Pretty print for slides/README. */
    public function asMultilineCommand(): string
    {
        $head = 'console importmap:require';
        $lines = [];
        foreach ($this->requires as $r) {
            $pkg = $r->packageWithConstraint(); // rarely used for importmap, but allowed
            $comment = $r->comment ? ' # ' . $r->comment : '';
            $lines[] = sprintf('       %s \%s', $pkg, $comment);
        }
        return $head . " \\\n" . implode("\n", $lines);
    }
}
