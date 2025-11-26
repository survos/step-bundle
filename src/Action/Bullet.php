<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use Survos\StepBundle\Metadata\Action;
use Survos\StepBundle\Util\ArtifactHelper;
use function Castor\io;
use function Castor\run;
use function Castor\task;


/** Declarative artifact to snapshot a file after an action runs. */
final class Bullet extends AbstractAction
{
    public function __construct(
        public string|array $msg = [],
        public ?bool $fade=null,
        public ?string $style='list', // li?
        public ?int $size= null,
        public ?string $header= null,
    ) {
        $this->msg = is_array($msg) ? $msg : [$msg];
        $this->fade ??= true;
        $this->size ??= max(1, 7-count($this->msg));
//        dd(count($this->msg), $this->size);
    }
    public ?bool $noop = true;

    public function summary(): string
    {
        return json_encode($this);
        // TODO: Implement summary() method.
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        // no-op
    }

    public function viewTemplate(): string
    {
        return "bullet.html.twig";
    }
}
