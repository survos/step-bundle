<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/**
 * Display a code snippet from a file or FQCN (optionally a method).
 * Selection can be by explicit lines or marker comments.
 */
final class DisplayCode extends Action
{
    private string $_target;
    public string $target { get => $this->_target; set => $this->_target = trim($value); }

    public ?string $method = null;

    public ?int $start = null;
    public ?int $end = null;

    public ?string $markerStart = null;
    public ?string $markerEnd = null;

    public ?string $lang = null;

    public function __construct(
        string $target,
        ?string $method = null,
        ?int $start = null,
        ?int $end = null,
        ?string $markerStart = null,
        ?string $markerEnd = null,
        ?string $lang = null,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->target = $target;
        $this->method = $method;
        $this->start = $start;
        $this->end = $end;
        $this->markerStart = $markerStart;
        $this->markerEnd = $markerEnd;
        $this->lang = $lang;
    }
}
