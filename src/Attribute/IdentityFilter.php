<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use ReflectionParameter;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Criteria;
use Switon\Http\CriteriaAttributeInterface;
use Switon\Principal\IdentityInterface;

/**
 * Declares action-level ownership filter for list and search criteria.
 *
 * Guidance: Put on controller methods that resolve {@see Criteria}; use {@see \Switon\Orm\Attribute\Owner}
 * for entity binding and set ownership in application code for create payloads.
 *
 * Road-signs:
 * - {@see CriteriaAttributeInterface}
 * - default {@see $subject} uses {@see IdentityInterface::getId()}
 *
 * @see \Switon\Http\CriteriaResolver
 */
#[Attribute(Attribute::TARGET_METHOD)]
class IdentityFilter implements CriteriaAttributeInterface
{
    #[Autowired] protected IdentityInterface $identity;

    public function __construct(
        public readonly string $field = 'created_by',
        public readonly string $subject = 'id',
    ) {
    }

    public function apply(Criteria $criteria, ReflectionParameter $parameter): void
    {
        $filters = $this->toFilters();
        if ($filters === null) {
            return;
        }

        $criteria->filters = [...$criteria->filters, ...$filters];
    }

    /**
     * @return array<string, int|string>|null
     */
    public function toFilters(): ?array
    {
        if ($this->field === '') {
            return null;
        }

        $value = $this->subject === 'name' ? $this->identity->getName() : $this->identity->getId();

        return [$this->field => $value];
    }
}
