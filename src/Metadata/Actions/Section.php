<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Visual grouping/cue (no-op for executors). */
final class Section extends Action
{
    private string $_title;
    public string $title { get => $this->_title; set => $this->_title = trim($value); }

    public function __construct(
        string $title,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->title = $title;
    }
}
