<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use ReflectionParameter;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Criteria;
use Switon\Http\CriteriaAttributeInterface;
use Switon\Principal\TenantInterface;

/**
 * Declares one server-side tenant filter for an action method.
 *
 * Use when a criteria filter should be forced from the current resolved tenant
 * instead of public request input.
 *
 * Road-signs:
 * - method annotation
 * - CriteriaResolver
 * - tenant id -> criteria filter
 *
 * @see \Switon\Http\CriteriaResolver::resolve()
 * @see \Switon\Http\Criteria
 * @see \Switon\Principal\TenantInterface
 */
#[Attribute(Attribute::TARGET_METHOD)]
class TenantFilter implements CriteriaAttributeInterface
{
    #[Autowired] protected TenantInterface $tenant;

    public function __construct(
        public readonly string $field = 'tenant_id',
    ) {
    }

    public function apply(Criteria $criteria, ReflectionParameter $parameter): void
    {
        $criteria->filters[$this->field] = $this->tenant->getId();
    }
}
