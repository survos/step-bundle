<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;

/**
 * composer require ... (dev or prod)
 */
final class ComposerRequire extends AbstractAction
{
    /** @param string[] $packages */
    public function __construct(
        public array $packages,
        public bool $dev = false,
        public ?string $note = null,
        public ?string $description = null,
        public ?string $cwd = null,
        public ?string $a = null, // artifact ID
        public bool $display = true, // display the slide
        public bool $run = true, // run the actions
        public ?int $size = null,
    )
    {
        $this->size ??= max(1, 7 - count($this->packages));
    }

    public function toCommand(): string
    {
        return sprintf(
            'composer req%s %s',
            $this->dev ? ' --dev' : '',
            implode(' ', $this->packages)
        );
    }

    public function summary(): string
    {
        return $this->toCommand();
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        $cmd = sprintf(
            'composer req%s %s',
            $this->dev ? ' --dev' : '',
            implode(' ', array_map('escapeshellarg', $this->packages))
        );
        new Bash($cmd, $this->note)->execute($ctx, $dryRun);
    }

    public function viewTemplate(): string { return 'composer.html.twig'; }

    public function viewContext(): array
    {
        return [
            'code' => $this->summary(),
            'lang' => 'bash',
            'packages' => $this->packages,
            'dev' => $this->dev,
            'note' => $this->note,
            'cwd'  => $this->cwd,
        ];
    }
}
