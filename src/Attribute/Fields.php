<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use ReflectionParameter;
use Switon\Http\Criteria;
use Switon\Http\CriteriaAttributeInterface;

/**
 * Declares allowed select fields for one action method.
 *
 * Road-signs:
 * - method annotation
 * - CriteriaResolver
 * - fields payload
 * - repository select
 *
 * @see \Switon\Http\CriteriaResolver::resolve()
 * @see \Switon\Http\Criteria
 * @see \Switon\Http\Attribute\Filters
 * @see \Switon\Http\Attribute\Orders
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class Fields implements CriteriaAttributeInterface
{
    /**
     * @param list<string> $fields
     */
    public function __construct(
        public array $fields = []
    ) {
    }

    public function apply(Criteria $criteria, ReflectionParameter $parameter): void
    {
        $criteria->fields = $this->fields;
    }
}
