<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Run a shell command via /bin/sh (or bash -lc in executors). */
final class Bash extends Action
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
