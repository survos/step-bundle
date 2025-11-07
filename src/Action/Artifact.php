<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use Survos\StepBundle\Metadata\Action;
use Survos\StepBundle\Util\ArtifactHelper;
use function Castor\io;
use function Castor\run;
use function Castor\task;


/** Declarative artifact to snapshot a file after an action runs. */
final class Artifact extends AbstractAction
{
    public function __construct(
        public string $sourcePath,        // e.g. "src/Controller/AppController.php" (relative to working dir)
        public string $asName,            // e.g. "AppController.php"
        public string $type = 'text/plain',
        public ?string $note = null
    ) {}

    public function summary(): string
    {
        return json_encode($this);
        // TODO: Implement summary() method.
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {

        $helper =  ArtifactHelper::fromTaskContext(task(), $ctx);
        $artifactFilename = $helper->save($this->sourcePath, $this->asName);
//        $source = $ctx->workingDirectory . '/' . $this->sourcePath;
    }

    public function viewTemplate(): string
    {
        return "artifact.html.twig";
    }
}
