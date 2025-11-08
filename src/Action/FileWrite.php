<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use Survos\StepBundle\Util\ArtifactHelper;
use Survos\StepBundle\Util\PathUtil;
use function Castor\io;
use function Castor\task;

/**
 * Write a file verbatim.
 */
final class FileWrite extends AbstractAction
{
    public function __construct(
        public string $path,
        public string $content,
        public ?string $note = null,
        public ?string $a = null,
    ) {}

    public function summary(): string
    {
        return sprintf('Write file %s', $this->path);
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        $abs = PathUtil::absPath($this->path, (string)$ctx->workingDirectory);
        if ($dryRun) {
            io()->writeln(sprintf("<comment>DRY</comment> write %s (%d bytes)", $abs, \strlen($this->content)));
            return;
        }
        PathUtil::ensureDir(\dirname($abs));
        file_put_contents($abs, $this->content);

        if ($this->a) {
            $helper = ArtifactHelper::fromTaskContext(task(), $ctx);
            $artifactLocation = $helper->save($this->a, $this->content);
            io()->writeln($artifactLocation . " written");
        }

    }

    public function viewTemplate(): string { return 'display_code.html.twig'; }

    public function viewContext(): array
    {
        return [
            'path' => $this->path,
            'code' => $this->content,
            'lang' => self::guessLangFromPath($this->path),
            'note' => $this->note,
        ];
    }

    public static function guessLangFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'php' => 'php',
            'yml', 'yaml' => 'yaml',
            'twig' => 'twig',
            'js', 'mjs' => 'js',
            'ts' => 'ts',
            'json' => 'json',
            'css' => 'css',
            'html', 'htm' => 'html',
            default => 'text',
        };
    }
}
