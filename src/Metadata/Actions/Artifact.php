<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

/** Declarative artifact to snapshot a file after an action runs. */
final class Artifact
{
    public function __construct(
        public string $sourcePath,        // e.g. "src/Controller/AppController.php" (relative to working dir)
        public string $asName,            // e.g. "AppController.php"
        public string $type = 'text/plain'
    ) {}
}
