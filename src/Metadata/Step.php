<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata;

use Attribute;

/**
 * Neutral, renderer-agnostic step descriptor.
 * A Step contains ordered Actions and presentation info.
 */
#[Attribute(Attribute::TARGET_FUNCTION)]
final class Step
{
    /**
     * @param list<string> $bullets
     * @param list<Action> $actions
     * @param list<string> $notes
     */
    public function __construct(
        public string $title,
        public string $description = '',
        public array $bullets = [],
        public array $actions = [],
        public ?string $website = null,
        public ?string $image = null,
        public array $notes = [],
        public ?string $group = null,
        public ?string $tags = null,
    ) {}

    public function hasContent(): bool
    {
        return $this->description !== '' || $this->bullets !== [] || $this->actions !== [] || $this->notes !== [];
    }
}
