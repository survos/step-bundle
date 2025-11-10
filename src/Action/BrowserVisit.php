<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use Survos\StepBundle\Util\ArtifactHelper;
use function Castor\{io, context, task};
use Symfony\Component\Panther\Client;

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
        public ?string $a = null,
    ) {}

    public function summary(): string
    {
        return sprintf('Open %s%s', $this->host ? rtrim($this->host, '/') : '', $this->path);
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        // using symfony/panther
        $client = Client::createChromeClient(
            null,
            [
                '--window-size=1500,4000',
                '--proxy-server=http://127.0.0.1:7080'
            ]
        );
        $url = $this->host . $this->path;
//        $host = parse_url($url, PHP_URL_HOST);
        io()->warning($url);
        $helper = ArtifactHelper::fromTaskContext(task(), $ctx);
        if ($this->a) {
            $client->request('GET', $url);

//            $artifactLocation = $helper->save($this->a, $output->getOutput());
            $artifactLocation = artifact_path($this->a);
            $client->takeScreenshot($artifactLocation);
            io()->writeln($artifactLocation . " written");
        } else {
            io()->error("No artifact name, why take a screenshot?");
        }

        // old way, actually openn  Debatable, use symfony open:local --path
//        $cmd = sprintf('xdg-open %s >/dev/null 2>&1 || open %s >/dev/null 2>&1 || true', escapeshellarg($url), escapeshellarg($url));
//        (new Bash($cmd, 'Open in browser'))->execute($ctx, $dryRun);
    }

    public function viewTemplate(): string { return 'browser_visit.html.twig'; }

    public function viewContext(): array
    {
        return ['url' => ($this->host ? rtrim($this->host, '/') : '') . $this->path, 'note' => $this->note];
    }
}
