<?php

namespace Survos\StepBundle\Attribute;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Symfony\Component\Console\Completion\CompletionInput;
use Castor\Attribute\AsCommandArgument;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Opt extends AsOption
{
    /**
     * @param array<string>|callable(CompletionInput): array<string>|null $autocomplete
     */
    public function __construct(
        public readonly string $info = '',
        public readonly mixed $auto = null,
        public ?string $short = null,
        ?string $name = null,
    ) {
//        public function __construct(?string $name = null, public readonly string|array|null $shortcut = null, public readonly ?int $mode = null, public readonly string $description = '', public readonly mixed $autocomplete = null)

        parent::__construct(name: $name, shortcut: $this->short, description: $info, autocomplete: $auto);
    }
}
