<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Click a link/button by visible text or selector. */
final class BrowserClick extends Action
{
    private string $_target;
    public string $target { get => $this->_target; set => $this->_target = trim($value); }

    public function __construct(
        string $target,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->target = $target;
    }
}
