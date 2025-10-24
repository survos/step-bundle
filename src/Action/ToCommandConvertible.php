<?php declare(strict_types=1);

namespace Survos\StepBundle\Action;

use Castor\Context;

/**
 * Any action that can be executed by RunStep should implement this.
 * Return:
 *  - string|array => executed by Castor\run()
 *  - null         => skipped (e.g., guard condition not met)
 */
interface ToCommandConvertible
{
    /** @return string|array|null */
    public function toCommand(Context $ctx): string|array|null;
}
