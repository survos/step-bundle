<?php

declare(strict_types=1);

namespace Survos\StepBundle\Attribute;

use Castor\Attribute\AsOption;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Opt extends AsOption
{
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
