<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;

/**
 * Add importmap packages (symfony/importmap).
 */
final class ImportmapRequire extends AbstractAction
{
    /** @param array<string,string|null>|string[] $packages */
    public function __construct(
        public array $packages,
        public ?string $note = null,
        public ?string $cwd = null,
    ) {}

    public function summary(): string
    {
        $names = array_keys(self::isAssoc($this->packages) ? $this->packages : array_fill_keys($this->packages, null));
        return 'importmap: ' . implode(', ', $names);
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        $parts = [];
        if (self::isAssoc($this->packages)) {
            foreach ($this->packages as $name => $ver) {
                $parts[] = $ver ? sprintf('%s@%s', $name, $ver) : $name;
            }
        } else {
            $parts = $this->packages;
        }
        $cmd = 'php bin/console importmap:require ' . implode(' ', array_map('escapeshellarg', $parts));
        (new Bash($cmd, $this->note, $this->cwd))->execute($ctx, $dryRun);
    }

    public function viewTemplate(): string { return 'importmap_require.html.twig'; }

    public function viewContext(): array
    {
        return [
            'code' => 'bin/console importmap:require ' .
                      implode(' ', self::isAssoc($this->packages)
                        ? array_map(fn($n,$v)=>$v?("$n@$v"):$n, array_keys($this->packages), $this->packages)
                        : $this->packages),
            'lang' => 'bash',
            'packages' => $this->packages,
            'note' => $this->note,
            'cwd'  => $this->cwd,
        ];
    }

    private static function isAssoc(array $a): bool
    {
        return $a !== [] && array_keys($a) !== range(0, count($a) - 1);
    }
}
