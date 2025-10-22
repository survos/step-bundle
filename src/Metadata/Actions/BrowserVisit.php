<?php declare(strict_types=1);
// file: src/Metadata/Actions/BrowserVisit.php — adds useProxy/host support

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

final class BrowserVisit extends Action
{
    /**
     * @param string      $urlOrPath Absolute URL (http/https) or path (e.g. "/")
     * @param string|null $note      Optional note for the slide
     * @param string|null $cwd       Working directory for context
     * @param bool        $useProxy  If true, rewrite URL to Symfony local proxy domain
     * @param string|null $host      Override domain (e.g. "barcode.wip"); if null, derive from context env
     */
    public function __construct(
        public string $urlOrPath,
        ?string $note = null,
        ?string $cwd = null,
        public bool $useProxy = false,
        public ?string $host = null,
    ) {
        parent::__construct($note, $cwd);
    }
}
