<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Composer command (e.g., "require foo/bar:^1.2"). */
final class Composer extends Action
{
    private string $_cmd;
    public string $cmd { get => $this->_cmd; set => $this->_cmd = trim($value); }

    public function __construct(
        string $cmd,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->cmd = $cmd;
    }
}
