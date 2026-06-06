<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use ReflectionParameter;
use Switon\Http\Criteria;
use Switon\Http\CriteriaAttributeInterface;

/**
 * Declares default order definitions for one action method.
 *
 * Road-signs:
 * - method annotation
 * - CriteriaResolver
 * - orders payload
 * - repository orderBy
 *
 * @see \Switon\Http\CriteriaResolver::resolve()
 * @see \Switon\Http\Criteria
 * @see \Switon\Http\Attribute\Fields
 * @see \Switon\Http\Attribute\Filters
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class Orders implements CriteriaAttributeInterface
{
    /**
     * @param array<string, int> $orders
     */
    public function __construct(
        public array $orders = []
    ) {
    }

    public function apply(Criteria $criteria, ReflectionParameter $parameter): void
    {
        $criteria->orders = $this->orders;
    }
}
