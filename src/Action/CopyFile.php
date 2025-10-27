<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use function Castor\{io,fs,context};

/**
 * Show code from a path (presentational).
 */
final class CopyFile extends AbstractAction
{
    public function __construct(
        public string $src,
        public ?string $target
    ) {}

    public function summary(): string
    {
        // @todo: check dirs!
        $target = context()->workingDirectory . '/' . $this->target;
        $targetDir = pathinfo($target, PATHINFO_DIRNAME);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        return sprintf('cp %s %s', $this->src, $this->target, );
    }

    public function toCommand(): ?string
    {
        return $this->summary();
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        assert(false, "when do we even use execute?");
        // No side-effects; presentational.
        io()->writeln(sprintf('Display: %s', $this->path));
    }

    public function viewTemplate(): string { return 'display_code.html.twig'; }

    public function viewContext(): array
    {
        $code = @file_get_contents($this->path) ?: '';
        return [
            'path' => $this->path,
            'code' => $code,
            'lang' => $this->lang ?? FileWrite::guessLangFromPath($this->path),
            'note' => $this->note,
        ];
    }
}
