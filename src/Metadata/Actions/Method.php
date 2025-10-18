<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/**
 * Create or replace a method body in a target class.
 * Minimal metadata; executors will handle formatting/placement.
 *
 * Example:
 *   new Method(App\Service\LoadService::class, method: 'load', body: <<<'PHP'
 *     foreach ($this->taskRepository->findAll() as $task) {}
 *   PHP)
 */
final class Method extends Action
{
    private string $_targetFqcn;
    public string $targetFqcn { get => $this->_targetFqcn; set => $this->_targetFqcn = ltrim($value, '\\'); }

    private string $_method;
    public string $method { get => $this->_method; set => $this->_method = trim($value); }

    private string $_body;
    public string $body { get => $this->_body; set => $this->_body = rtrim($value) . "\n"; }

    public ?string $visibility = 'public'; // hint only; executor may honor
    public ?string $returnType = null;     // hint only
    /** @var list<string> param snippets like "int $id", "string $name = null" */
    public array $params = [];

    /**
     * @param list<string> $params
     */
    public function __construct(
        string $targetFqcn,
        string $method,
        string $body,
        array $params = [],
        ?string $visibility = 'public',
        ?string $returnType = null,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->targetFqcn = $targetFqcn;
        $this->method     = $method;
        $this->body       = $body;
        $this->params     = $params;
        $this->visibility = $visibility;
        $this->returnType = $returnType;
    }
}
