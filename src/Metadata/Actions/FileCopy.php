<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Copy a file. */
final class FileCopy extends Action
{
    private string $_from;
    public string $from { get => $this->_from; set => $this->_from = ltrim(trim($value), './'); }

    private string $_to;
    public string $to { get => $this->_to; set => $this->_to = ltrim(trim($value), './'); }

    public function __construct(
        string $from,
        string $to,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->from = $from;
        $this->to = $to;
    }
}
