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
        public int $sleep = 1,
        public ?string $a = null,
        public bool $displayOnly = false,
    ) {}

    public function summary(): string
    {
        return sprintf('Open %s%s', $this->host ? rtrim($this->host, '/') : '', $this->path);
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        if ($this->displayOnly) {
            return;
        }
        $settings =  [
            '--window-size=1024,2048',
        ];
        if ($this->host && str_contains($this->host, '.wip')) {
            $settings[] = '--proxy-server=http://127.0.0.1:7080';
        }

        // using symfony/panther
        $client = Client::createChromeClient(
            null,
            $settings,
        );
        $url = $this->host . $this->path;
//        $host = parse_url($url, PHP_URL_HOST);
        io()->warning($url);
        $helper = ArtifactHelper::fromTaskContext(task(), $ctx);
        if ($this->a) {
            $client->request('GET', $url);
            sleep($this->sleep); // Wait 2 seconds for content to load
//            $artifactLocation = $helper->save($this->a, $output->getOutput());
            $artifactLocation = artifact_path($this->a);
            $response = $client->takeScreenshot($artifactLocation);
//            dd $artifactLocation);
            io()->writeln($artifactLocation . " written");
        } else {
            // could just be for slideshow, e.g. open api keys
//            io()->error("No artifact name, why take a screenshot?");
        }

        // old way, actually open  Debatable, use symfony open:local --path
//        $cmd = sprintf('xdg-open %s >/dev/null 2>&1 || open %s >/dev/null 2>&1 || true', escapeshellarg($url), escapeshellarg($url));
//        (new Bash($cmd, 'Open in browser'))->execute($ctx, $dryRun);
    }

    public function viewTemplate(): string { return 'browser_visit.html.twig'; }

    public function viewContext(): array
    {
        return ['url' => ($this->host ? rtrim($this->host, '/') : '') . $this->path, 'note' => $this->note];
    }
}
