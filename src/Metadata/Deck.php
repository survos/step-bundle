<?php

declare(strict_types=1);

namespace Survos\StepBundle\Metadata;

/**
 * Describes the deck a castor file defines: what the title slide says, and optionally a logo and a closing slide.
 * Put it on any function in the file. Without it a deck gets a plain title slide built from its code.
 *
 *     #[Deck(title: 'Native workflows', subtitle: 'Places, transitions and a diagram', author: 'Tac Tacelosky')]
 *     function deck(): void {}
 */
#[\Attribute(\Attribute::TARGET_FUNCTION)]
final class Deck
{
    /**
     * @param string|null           $logo    asset path shown on the title slide, every slide and the closing slide
     * @param string|null           $closing heading for a closing slide (e.g. "THANK YOU"); no closing slide when null
     * @param array<string, string> $contact label => value lines on the closing slide; http(s) values and e-mail
     *                                       addresses become links
     */
    public function __construct(
        public string $title = '',
        public ?string $subtitle = null,
        public ?string $author = null,
        public ?string $logo = null,
        public ?string $closing = null,
        public array $contact = [],
    ) {
    }
}
