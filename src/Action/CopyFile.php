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
            if (!mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
            }
        }
        return sprintf('cp %s %s', $this->src, $this->target, );
    }

    public function toCommand(): ?string
    {
        return $this->summary();
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        fs()->copy($this->src, $targetFile = context()->workingDirectory . '/' . $this->target);
//        dd($this->src, $targetFile, file_get_contents($targetFile));
        // No side-effects; presentational.
        io()->writeln(sprintf('Display: %s', realpath($targetFile)));
    }

    public function viewTemplate(): string { return 'display_code.html.twig'; }

    public function viewContext(): array
    {
        assert($this->src, "Empty path");
        assert(file_exists($this->src), "Missing $this->src");
        $code = file_get_contents($this->src);
        return [
            'path' => $this->src,
            'code' => $code,
            'lang' => $this->lang ?? FileWrite::guessLangFromPath($this->src),
            'note' => "Copy from $this->src to $this->target",
        ];
    }
}
