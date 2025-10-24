<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use Symfony\Component\Yaml\Yaml;
use Survos\StepBundle\Util\PathUtil;
use function Castor\io;

/**
 * Merge/replace YAML config.
 */
final class YamlWrite extends AbstractAction
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        public string $path,
        public ?array $data=null, // pass in data or content, not both
        public bool $merge = true,
        public int $inline = 4,
        public ?string $note = null,
        public ?string $content = null, // raw YAML
    ) {}

    public function summary(): string
    {
        return sprintf('Write YAML %s (%s)', $this->path, $this->merge ? 'merge' : 'replace');
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        $abs = PathUtil::absPath($this->path, (string)$ctx->workingDirectory);
        $existing = [];
        if ($this->merge && is_file($abs)) {
            $existing = Yaml::parseFile($abs) ?? [];
            if (!is_array($existing)) { $existing = []; }
        }
        $final = $this->merge ? array_replace_recursive($existing, $this->data) : $this->data;

        $out = Yaml::dump($final, $this->inline);
        if ($dryRun) {
            io()->writeln(sprintf("<comment>DRY</comment> write %s\n%s", $abs, $out));
            return;
        }
        PathUtil::ensureDir(\dirname($abs));
        file_put_contents($abs, $out);
    }

    public function viewTemplate(): string { return 'yaml_write.html.twig'; }

    public function viewContext(): array
    {
        return [
            'path' => $this->path,
            'code' => Yaml::dump($this->data, $this->inline),
            'lang' => 'yaml',
            'merge'=> $this->merge,
            'note' => $this->note,
        ];
    }
}
