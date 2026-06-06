<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Binding\Attribute\ResolvedBy;

/**
 * Structured query criteria for repository-style list queries.
 *
 * Use as an action parameter when one list endpoint should combine request-derived filters
 * with action-declared keyword, scope, order, or field payloads.
 *
 * @see \Switon\Http\CriteriaResolver
 * @see \Switon\Http\Attribute\Filters
 * @see \Switon\Http\Attribute\Orders
 * @see \Switon\Http\Attribute\Fields
 * @see \Switon\Http\Attribute\Keyword
 */
#[ResolvedBy(CriteriaResolver::class)]
class Criteria
{
    /** @var array<string, mixed> */
    public array $filters = [];

    /** @var array<string, int> */
    public array $orders = [];

    /** @var list<string> */
    public array $fields = [];
}
