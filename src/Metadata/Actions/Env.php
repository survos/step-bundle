<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Write/update a key=value in an env file (.env, .env.local by default). */
final class Env extends Action
{
    private string $_key;
    public string $key { get => $this->_key; set => $this->_key = strtoupper(str_replace([' ', '-'], '_', trim($value))); }

    private string $_value;
    public string $value { get => $this->_value; set => $this->_value = trim($value); }

    private ?string $_file;
    public ?string $file { get => $this->_file; set => $this->_file = $value !== null ? ltrim(trim($value), './') : null; }

    public function __construct(
        string $key,
        string $value,
        ?string $file = '.env.local',
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->key = $key;
        $this->value = $value;
        $this->file = $file;
    }
}
