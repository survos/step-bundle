<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use Survos\StepBundle\Util\ArtifactHelper;
use function Castor\io;

use Survos\StepBundle\Util\EnvUtil;
use function Castor\task;

final class PregReplace extends AbstractAction
{
    public function __construct(
        /** PCRE pattern, e.g. '#\[MeiliIndex\]#' */
        private(set) string $pattern = '',

        /** Replacement string for preg_replace */
        private(set) string $replacement = '',

        /** Relative or absolute path to the file */
        private(set) string $file = '',

        /** preg_replace limit, -1 = unlimited */
        private(set) int $limit = -1,

        private(set) ?string $note = null,
        private(set) ?string $cwd = null,
        public ?string $a = null, // artifact
    ) {
    }

    public function toCommand(): ?string
    {
        $pattern = $this->pattern !== '' ? $this->pattern : '(no pattern set)';
        $file    = $this->file    !== '' ? $this->file    : '(no file set)';

        return sprintf(
            "preg_replace %s → %s in %s",
            $pattern,
            $this->replacement,
            $file
        );
    }

    public function summary(): string
    {
        return sprintf(
            'preg_replace on %s (pattern: %s)',
            $this->file !== '' ? $this->file : '(no file)',
            $this->pattern !== '' ? $this->pattern : '(no pattern)'
        );
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        if ($this->pattern === '') {
            throw new \InvalidArgumentException('PregReplace: $pattern must not be empty.');
        }

        if ($this->file === '') {
            throw new \InvalidArgumentException('PregReplace: $file must not be empty.');
        }

        // Resolve file path relative to workingDirectory / cwd if not absolute
        $filePath = $this->file;
        if (!self::isAbsolutePath($filePath)) {
            $baseDir  = $this->cwd ?? $ctx->workingDirectory;
            $filePath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filePath;
        }

        if ($dryRun) {
            if (!is_file($filePath)) {
                throw new \RuntimeException("File does not exist (dry-run): $filePath");
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read file (dry-run): $filePath");
            }

            preg_replace($this->pattern, $this->replacement, $content, $this->limit, $count);

            io()->info(sprintf(
                '[dry-run] Would apply preg_replace %s → %s on %s (%d replacements)',
                $this->pattern,
                $this->replacement,
                $filePath,
                $count
            ));

            return;
        }

        // Real execution using the new EnvUtil::pregReplaceInFile signature
        $count = EnvUtil::pregReplaceInFile(
            $this->pattern,
            $this->replacement,
            $filePath,
            $this->limit
        );
            // @todo: move this to castor listener
            $helper = ArtifactHelper::fromTaskContext(task(), $ctx);
            if ($this->a) {
                $artifactLocation = $helper->save($this->a, file_get_contents($filePath));
                io()->writeln($artifactLocation . " written");
            }

        if ($count > 0) {
            io()->success(sprintf(
                'Applied preg_replace %s → %s on %s (%d replacements)',
                $this->pattern,
                $this->replacement,
                $filePath,
                $count
            ));
        } else {
            io()->warning(sprintf(
                'No matches for pattern %s in %s',
                $this->pattern,
                $filePath
            ));
        }
    }

    public function viewTemplate(): string { return 'preg_replace.html.twig'; }


    private static function isAbsolutePath(string $path): bool
    {
        // Unix absolute or Windows "C:\" / "C:/" style
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1);
    }
}
