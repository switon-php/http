<?php

declare(strict_types=1);

namespace Switon\Http;

use ReflectionParameter;

/**
 * Contract for method attributes that contribute to resolved Criteria payload.
 *
 * Use when an HTTP method attribute wants to apply its own criteria mutation
 * instead of being hardcoded inside <code>CriteriaResolver</code>.
 *
 * Road-signs:
 * - one method attribute = one criteria mutation unit
 * - <code>CriteriaResolver</code> creates the attribute through <code>make()</code>
 * - <code>apply()</code> may read injected services and mutate <code>Criteria</code>
 * - fixed public inputs and server-side enforced filters can share the same contract
 *
 * @see \Switon\Http\CriteriaResolver
 * @see \Switon\Http\Criteria
 */
interface CriteriaAttributeInterface
{
    /**
     * Apply this attribute's criteria mutation for one action parameter.
     */
    public function apply(Criteria $criteria, ReflectionParameter $parameter): void;
}
