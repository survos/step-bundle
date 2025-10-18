<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/**
 * Add one or more `use` imports to a target class file.
 *
 * Example:
 *   new AddUse(App\Service\LoadService::class, App\Entity\Task::class)
 *   new AddUse(App\Service\LoadService::class, [App\Entity\Task::class, App\Repository\TaskRepository::class])
 */
final class AddUse extends Action
{
    private string $_targetFqcn;
    public string $targetFqcn { get => $this->_targetFqcn; set => $this->_targetFqcn = ltrim($value, '\\'); }

    /** @var list<string> */
    public array $imports;

    /**
     * @param string|string[] $imports one or many FQCNs
     */
    public function __construct(
        string $targetFqcn,
        string|array $imports,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->targetFqcn = $targetFqcn;
        $list = \is_array($imports) ? $imports : [$imports];
        $this->imports = array_map(static fn($fqcn) => ltrim((string)$fqcn, '\\'), $list);
    }
}
