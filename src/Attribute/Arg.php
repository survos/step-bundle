<?php

namespace Survos\StepBundle\Attribute;

use Castor\Attribute\AsArgument;
use Symfony\Component\Console\Completion\CompletionInput;
use Castor\Attribute\AsCommandArgument;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Arg extends AsArgument
{
    /**
     * @param array<string>|callable(CompletionInput): array<string>|null $autocomplete
     */
    public function __construct(
        public readonly string $info = '',
        public readonly mixed $auto = null,
        ?string $name = null,
    ) {
        parent::__construct($name, $info, $auto);
    }
}
