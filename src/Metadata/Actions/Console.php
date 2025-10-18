<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Symfony Console command (executor may auto-detect bin/console vs symfony console). */
final class Console extends Action
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
