<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use function Castor\{io, context};

/**
 * Show code from a path (presentational).
 */
final class DisplayCode extends AbstractAction
{
    public function __construct(
        public string $path,
        public ?string $lang = null,
        public ?string $note = null,
    ) {
//        dd($this, $this->highlightLanguage);
    }
    public string $highlightLanguage { get => $this->lang; }

    public function summary(): string
    {
        return sprintf('Show %s', $this->path);
    }

    public function toCommand(): ?string
    {

//        if (!file_exists($this->path)) {
//            io()->writeln(sprintf("Display contents of %s when it exists", $this->path)));
//        }
////        return "cat $this->path";
//        io()->writeln(sprintf('Display: %s', $this->path));
//        $code = file_get_contents($this->path);
//        io()->writeln($code);
        return 'cat ' . $this->path;
        // @todo: figure out slide formatting v. console output
        return $code;
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        // No side-effects; presentational.
        io()->writeln(sprintf('Display: %s', $this->path));
    }

    public function viewTemplate(): string {
        return 'display_code.html.twig';
    }

    public function viewContext(): array
    {
//        dd(context()->workingDirectory());
//        dd($this);


        if (!file_exists($this->path)) {
            $code = "File $this->path does not exist. maybe run castor?";
        } else {
            $code = file_get_contents($this->path);
        }

        return [
            'path' => $this->path,
            'code' => $code,
            'lang' => $this->lang ?? FileWrite::guessLangFromPath($this->path),
            'note' => $this->note,
        ];
    }
}
