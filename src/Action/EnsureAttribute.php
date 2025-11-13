<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;

/**
 *
 */
final class EnsureAttribute extends AbstractAction
{
    /** @param array<string,string|null>|string[] $packages */
    public function __construct(
        public string $name, // e.g. MeiliIndex
        public array $arguments=[], // e.,g ['primaryKey' = 'sku', 'searchable' => ['title']]
        public ?string $class=null, // e.g. Movie::class
        public ?string $property=null, // e.g. 'title' on public string $title;
        public ?string $method=null, // e.g. 'title' on public string $title;
        public ?string $filename =null, // e.g. src/Entity/Movie.php
        public string $mode = 'replace', // replace|delete|add
        public ?string $note = null,
        public ?string $a = null, // the artifact is the new attribute plus the class/property/method declatration
    ) {}

    public function summary(): string
    {
        return sprintf('#[%s(%s)] on %s', $this->name, json_encode($this->arguments), $this->class);
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        // read the class and parse it using nicik/php-parser

    }

    public function viewTemplate(): string { return 'ensure_attribute.html.twig'; }

    public function viewContext(): array
    {
        return [
            'code' => 'php bin/console importmap:require ' .
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
