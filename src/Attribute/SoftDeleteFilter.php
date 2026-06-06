<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use ReflectionParameter;
use Switon\Http\Criteria;
use Switon\Http\CriteriaAttributeInterface;

/**
 * Declares one server-side soft-delete filter for an action method.
 *
 * Use when a criteria filter should force the framework soft-delete contract
 * of <code>0 = not deleted</code> instead of public request input.
 *
 * Road-signs:
 * - method annotation
 * - CriteriaResolver
 * - deleted_at = 0 -> criteria filter
 *
 * @see \Switon\Http\CriteriaResolver::resolve()
 * @see \Switon\Http\Criteria
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class SoftDeleteFilter implements CriteriaAttributeInterface
{
    public function __construct(
        public string $field = 'deleted_at',
    ) {
    }

    public function apply(Criteria $criteria, ReflectionParameter $parameter): void
    {
        $criteria->filters[$this->field] = 0;
    }
}
