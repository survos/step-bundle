<?php declare(strict_types=1);

namespace Survos\StepBundle\Metadata\Actions;

/**
 * Simple record for a required package (Composer or Importmap).
 *
 * Example:
 *   new RequirePackage('survos/step-bundle', 'Step authoring bundle')
 *   new RequirePackage('@tabler/core', 'Tabler UI')
 */
final class RequirePackage
{
    private string $_package;
    public string $package { get => $this->_package; set => $this->_package = trim($value); }

    public ?string $comment = null;

    private ?string $_constraint = null;
    public ?string $constraint { get => $this->_constraint; set => $this->_constraint = $value !== null ? trim($value) : null; }

    public function __construct(string $package, ?string $comment = null, ?string $constraint = null)
    {
        $this->package    = $package;
        $this->comment    = $comment;
        $this->constraint = $constraint;
    }

    public function packageWithConstraint(): string
    {
        return $this->constraint ? "{$this->package}:{$this->constraint}" : $this->package;
    }
}
