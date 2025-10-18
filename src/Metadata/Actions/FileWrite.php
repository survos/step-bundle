<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/** Write arbitrary text content to a file path. */
final class FileWrite extends Action
{
    private string $_path;
    public string $path { get => $this->_path; set => $this->_path = ltrim(trim($value), './'); }

    private string $_content;
    public string $content { get => $this->_content; set => $this->_content = (string)$value; }

    public function __construct(
        string $path,
        string $content,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->path = $path;
        $this->content = $content;
    }
}
