<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/**
 * Aggregate "composer require" arguments with comments for pretty slide rendering.
 *
 * Example:
 *   new ComposerRequire(
 *       requires: [
 *           new Require('survos/step-bundle', 'Step authoring bundle'),
 *           new Require('easycorp/easyadmin-bundle', 'Easy Administration, PHP based'),
 *       ],
 *       dev: false,
 *       cwd: 'demo'
 *   )
 */
final class ComposerRequire extends Action
{
    /** @var list<Require> */
    public array $requires;

    public bool $dev = false;

    public function __construct(
        array $requires,
        ?bool $dev = false,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->requires = $requires;
        $this->dev      = (bool) $dev;
    }

    /** Slide/README pretty print. Uses "composer req" for brevity. */
    public function asMultilineCommand(): string
    {
        $head = 'composer req' . ($this->dev ? ' --dev' : '');
        $lines = [];
        foreach ($this->requires as $r) {
            $pkg = $r->packageWithConstraint();
            $comment = $r->comment ? ' # ' . $r->comment : '';
            $lines[] = sprintf('       %s \%s', $pkg, $comment);
        }
        return $head . " \\\n" . implode("\n", $lines);
    }
}
