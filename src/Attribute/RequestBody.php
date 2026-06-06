<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use Switon\Binding\Attribute\ResolvedBy;
use Switon\Http\RequestBodyResolver;

/**
 * Marks a typed input class as resolvable from HTTP body input only.
 *
 * Use when action parameters should be populated and validated from parsed POST/JSON fields.
 *
 * Behavior summary:
 * - The value resolver maps body fields to public input properties.
 * - Non-nullable properties without default values are treated as required.
 * - Validation constraints on properties are applied during population.
 *
 * @see \Switon\Http\RequestBodyResolver::resolve()
 * @see \Switon\Http\Attribute\RequestData
 * @see \Switon\Http\Attribute\RequestQuery
 * @see \Switon\Binding\ObjectResolver::resolve()
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RequestBody extends ResolvedBy
{
    public function __construct()
    {
        parent::__construct(RequestBodyResolver::class);
    }
}
