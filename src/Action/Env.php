<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;
use function Castor\io;

use Survos\StepBundle\Action\AbstractAction;
use Survos\StepBundle\Metadata\Action;
use Survos\StepBundle\Util\EnvUtil;

/** Write/update a key=value in an env file (.env, .env.local by default). */
final class Env extends AbstractAction
{
//    private string $_key;
//    public string $key { get => $this->_key; set => $this->_key = strtoupper(str_replace([' ', '-'], '_', trim($value))); }
//
//    private string $_value;
//    public string $value { get => $this->_value; set => $this->_value = trim($value); }
//
//    private ?string $_file;
//    public ?string $file { get => $this->_file; set => $this->_file = $value !== null ? ltrim(trim($value), './') : null; }

    public function __construct(
        private(set) string $key,
        public array $cfg,
        private(set) ?string $file = '.env.local',
        private(set) ?string $note = null,
        private(set) ?string $cwd = null,
    ) {
//        parent::__construct($note, $cwd);
        $this->key = $key;
//        $this->value = $value;
        $this->file = $file;
    }

    public function summary(): string
    {
        return __METHOD__;
        // TODO: Implement summary() method.
    }

    public function execute(Context $ctx, bool $dryRun = false): void
    {
        EnvUtil::ensureEnvVars($ctx->workingDirectory, [$this->key => $this->cfg], fn() => io()->ask("value of " . $this->key, $this->cfg['default'] ?? null));
    }

    public function viewTemplate(): string
    {
        return json_encode($this);
        // TODO: Implement viewTemplate() method.
    }
}
