<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Open an external URL, or a Symfony route (executor may resolve route+params). */
final class OpenUrl extends Action
{
    private string $_urlOrRoute;
    public string $urlOrRoute { get => $this->_urlOrRoute; set => $this->_urlOrRoute = trim($value); }

    /** @var array<string, scalar|null|array> */
    public array $params;

    public function __construct(
        string $urlOrRoute,
        array $params = [],
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->urlOrRoute = $urlOrRoute;
        $this->params = $params;
    }
}
