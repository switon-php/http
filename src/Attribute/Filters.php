<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use ReflectionParameter;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Criteria;
use Switon\Http\CriteriaAttributeInterface;
use Switon\Http\RequestInterface;

/**
 * Declares allowed request filter keys for one action method.
 *
 * Road-signs:
 * - method annotation
 * - CriteriaResolver
 * - filters payload
 * - request filters()
 *
 * @see \Switon\Http\CriteriaResolver::resolve()
 * @see \Switon\Http\Criteria
 * @see \Switon\Http\Attribute\Fields
 * @see \Switon\Http\Attribute\Orders
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Filters implements CriteriaAttributeInterface
{
    #[Autowired] protected RequestInterface $request;

    /**
     * @param list<string> $fields
     */
    public function __construct(
        public readonly array $fields = []
    ) {
    }

    public function apply(Criteria $criteria, ReflectionParameter $parameter): void
    {
        $criteria->filters = [...$this->request->filters($this->fields), ...$criteria->filters];
    }
}
