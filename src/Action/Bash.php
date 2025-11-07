<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use Survos\StepBundle\Util\ArtifactHelper;
use function Castor\{io,run,task};

/**
 * Run a shell command.
 */
final class Bash extends AbstractAction
{
    public function __construct(
        public string $command,
        public ?string $note = null,
        public ?string $cwd = null,
        public ?string $a = null, // artifact ID
    ) {}

    public string $highlightLanguage = 'bash';
    public ?string $artifact = null; // the content

    public function summary(): string
    {
        return $this->note ?: sprintf('$ %s', $this->command);
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        $execCtx = $this->cwd ? $ctx->withWorkingDirectory($this->cwd) : $ctx;
        if ($dryRun) {
            io()->writeln(sprintf('<comment>DRY</comment> (%S) $ %s', $execCtx->workingDirectory, $this->command));
            return;
        }
        try {
            $output = run($this->command, context: $execCtx, callback: fn($type, $buffer) => io()->write($buffer));
            // @todo: move this to castor listener
            $helper = ArtifactHelper::fromTaskContext(task(), $ctx);
            if ($this->a) {
                $artifactLocation = $helper->save($this->a, $output->getOutput());
                io()->writeln($artifactLocation . " written");
            }

        } catch (\Throwable $e) {
            dd($e);
        }

    }

//    public function toCommand(): string
//    {
//        return $this->command;
//    }

    public function viewTemplate(): string { return 'bash.html.twig'; }

    public function viewContext(): array
    {
        return ['code' => $this->command, 'lang' => 'bash', 'note' => $this->note, 'cwd' => $this->cwd];
    }
}
