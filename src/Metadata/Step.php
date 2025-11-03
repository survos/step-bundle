<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata;

use Attribute;
use Survos\StepBundle\Action\AbstractAction;

/**
 * Neutral, renderer-agnostic step descriptor.
 * A Step contains ordered Actions and presentation info.
 */
#[Attribute(Attribute::TARGET_FUNCTION|Attribute::IS_REPEATABLE)]
final class Step
{
    /**
     * @param list<string> $bullets
     * @param list<AbstractAction> $actions
     * @param list<string> $notes
     */
    public function __construct(
        public string $title='', // get from AsTask if empty
        public string $description = '',
        public array $bullets = [],
        public array $actions = [],
        public ?string $website = null,
        public ?string $image = null,
        public array $notes = [],
        public ?string $group = null,
        public ?string $tags = null,
        public array $options = [] {
            set(string|array $value) => is_string($value)
                ? explode('|', $value)
                : $value;
        }

    ) {}

    public function hasContent(): bool
    {
        return $this->description !== '' || $this->bullets !== [] || $this->actions !== [] || $this->notes !== [];
    }
}
