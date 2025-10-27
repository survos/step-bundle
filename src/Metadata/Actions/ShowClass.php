<?php declare(strict_types=1);

// src/Metadata/Actions/ShowClass.php

namespace Survos\StepBundle\Metadata\Actions;

use Survos\StepBundle\Metadata\Action;

/**
 * Display a class declaration and/or selected methods (signatures).
 * Execution-time renderer will use ReflectionClass (or BetterReflection) to extract.
 *
 * Examples:
 *   new ShowClass(App\Entity\Task::class, declarations: true, methods: ['__construct'])
 *   new ShowClass(App\Controller\TaskController::class, declarations: true, methods: ['__construct','listTasks'])
 */
final class ShowClass extends Action
{
    private string $_targetFqcn;
    public string $targetFqcn { get => $this->_targetFqcn; set => $this->_targetFqcn = ltrim($value, '\\'); }

    /** Show "class Foo implements Bar" etc. */
    public bool $declarations = false;

    /** @var list<string> method names to display (signatures only, no bodies) */
    public array $methods = [];

    public function __construct(
        string $targetFqcn,
        bool $declarations = false,
        array $methods = [],
        ?string $note = null,
        ?string $cwd = null,
    ) {
        parent::__construct($note, $cwd);
        $this->targetFqcn  = $targetFqcn;
        $this->declarations = $declarations;
        $this->methods = $methods;
    }
}
