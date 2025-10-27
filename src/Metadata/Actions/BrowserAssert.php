<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/**
 * Assert content/visibility/status in a browser step.
 * One of $contains or $equals is typically used.
 */
final class BrowserAssert extends Action
{
    private string $_selectorOrArea;
    public string $selectorOrArea { get => $this->_selectorOrArea; set => $this->_selectorOrArea = trim($value); }

    public ?string $contains = null;
    public ?string $equals = null;

    public function __construct(
        string $selectorOrArea,
        ?string $contains = null,
        ?string $equals = null,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->selectorOrArea = $selectorOrArea;
        $this->contains = $contains;
        $this->equals = $equals;
    }
}
