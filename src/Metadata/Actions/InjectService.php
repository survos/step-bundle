<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/**
 * Inject a service into a target class' constructor:
 *  - adds a `use` import for the service FQCN (executor should coordinate with AddUse)
 *  - adds a constructor parameter with inferred or explicit variable name
 *  - (optionally) adds a private property promotion or assignment (executor concern)
 *
 * Examples:
 *   new InjectService(App\Service\LoadService::class, Doctrine\ORM\EntityManagerInterface::class, '$em')
 *   new InjectService(App\Service\LoadService::class, App\Repository\TaskRepository::class) // → $taskRepository
 */
final class InjectService extends Action
{
    private string $_targetFqcn;
    public string $targetFqcn { get => $this->_targetFqcn; set => $this->_targetFqcn = ltrim($value, '\\'); }

    private string $_serviceFqcn;
    public string $serviceFqcn { get => $this->_serviceFqcn; set => $this->_serviceFqcn = ltrim($value, '\\'); }

    private ?string $_varName;
    /** Variable name including `$` (executor may infer when null). */
    public ?string $varName { get => $this->_varName; set => $this->_varName = $value !== null ? (str_starts_with($value, '$') ? $value : '$'.$value) : null; }

    public function __construct(
        string $targetFqcn,
        string $serviceFqcn,
        ?string $varName = null,
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->targetFqcn  = $targetFqcn;
        $this->serviceFqcn = $serviceFqcn;
        $this->varName     = $varName;
    }
}
