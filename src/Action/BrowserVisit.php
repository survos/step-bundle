<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;

/**
 * Minimal "open URL" action.
 */
final class BrowserVisit extends AbstractAction
{
    public function __construct(
        public string $path,
        public ?string $host = null,
        public bool $useProxy = false,
        public ?string $note = null,
    ) {}

    public function summary(): string
    {
        return sprintf('Open %s%s', $this->host ? rtrim($this->host, '/') : '', $this->path);
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        $url = ($this->host ? rtrim($this->host, '/') : '') . $this->path;
        $cmd = sprintf('xdg-open %s >/dev/null 2>&1 || open %s >/dev/null 2>&1 || true', escapeshellarg($url), escapeshellarg($url));
        (new Bash($cmd, 'Open in browser'))->execute($ctx, $dryRun);
    }

    public function viewTemplate(): string { return 'browser_visit.html.twig'; }

    public function viewContext(): array
    {
        return ['url' => ($this->host ? rtrim($this->host, '/') : '') . $this->path, 'note' => $this->note];
    }
}
