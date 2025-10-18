<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Visit a URL in a browser-like step (maps well to Zenstruck Browser). */
final class BrowserVisit extends Action
{
    private string $_url;
    public string $url { get => $this->_url; set => $this->_url = trim($value); }

    public function __construct(
        string $url,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->url = $url;
    }
}
