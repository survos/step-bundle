<?php

declare(strict_types=1);

namespace Survos\StepBundle\Attribute;

use Castor\Attribute\AsArgument;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Arg extends AsArgument
{
    public function __construct(
        public readonly string $info = '',
        public readonly mixed $auto = null,
        ?string $name = null,
    ) {
        parent::__construct($name, $info, $auto);
    }
}
