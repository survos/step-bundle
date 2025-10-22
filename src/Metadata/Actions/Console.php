<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Symfony Console command (executor may auto-detect bin/console vs symfony console). */
final class Console extends Action
{
    /** human label for the command family within the step */
    public ?string $id;

    /** @var Artifact[] files to snapshot post-run */
    public array $artifacts;

    public function __construct(
        public string $cmd,
        ?string $note = null,
        ?string $cwd = null,
        ?string $id = null,
        array $artifacts = []
    ) {
        parent::__construct($note, $cwd);
        $this->cmd = trim($cmd);
        $this->id = $id;
        $this->artifacts = $artifacts;
    }
}
