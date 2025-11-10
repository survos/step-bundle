<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use Survos\StepBundle\Metadata\Step;
use Survos\StepBundle\Service\CastorStepExporter;
use Survos\StepBundle\Util\ArtifactHelper;
use Survos\StepBundle\Util\ArtifactWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use function Castor\{io, context, task};

/**
 * Show code from a path (presentational).
 */
final class DisplayArtifact extends AbstractAction
{
    public function __construct(
        public string $path,
        public ?string $step = null, // defaults to current step
        public ?string $lang = null,
        public ?string $note = null,
    ) {
//        dd($this, $this->highlightLanguage);
    }


    public string $highlightLanguage { get => $this->lang; }

    public function summary(): string
    {
        return sprintf('Show Artifact %s', $this->path);
    }

    public function toCommand(): ?string
    {
        return 'cat ' . $this->path;
        // @todo: figure out slide formatting v. console output
        return $code;
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        $artifact = artifact($this->path);
        // No side-effects; presentational.
        io()->writeln(sprintf('Display: %s', $this->path));
        // @todo: check if image, show only a snippet, etc.
//        io()->writeln($artifact);
    }

    public function viewTemplate(): string {
        $ext = pathinfo($this->path, PATHINFO_EXTENSION);
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg'])) {
            return 'display_image.html.twig';
        }
        return 'display_code.html.twig';
    }

    public function viewContext(): array
    {

//        $execCtx = $this->cwd ? $ctx->withWorkingDirectory($this->cwd) : $ctx;
//        $a = ArtifactHelper::fromTaskContext(task(), $execCtx);
//        $exporter = new CastorStepExporter(__DIR__);
//        $path = CastorStepExporter::ARTIFACT_ROOT . $this->project . '/' . $this->path;
//        $x = $this->artifactLocator->read($this->project, $this->path);
//
//        dd(context()->workingDirectory());
        $artifactFile = $this->artifactLocator->absolute($this->project, $this->path);

        if (!file_exists($artifactFile)) {
            $code = "File $artifactFile does not exist. maybe run castor?";
        } else {
            $code = file_get_contents($artifactFile);
        }

        $converter = new AnsiToHtmlConverter();
        $codeHtml = $converter->convert($code);

        return [
            'path' => $this->path,
            'code' => $code,
            'lang' => $this->lang ?? FileWrite::guessLangFromPath($this->path),
            'note' => $this->note,
        ];
    }
}
