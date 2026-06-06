<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use Switon\Binding\Attribute\ResolvedBy;
use Switon\Http\RequestQueryResolver;

/**
 * Marks a typed input class as resolvable from request query input only.
 *
 * Use when action parameters should bind only from query-string fields.
 *
 * @see \Switon\Http\RequestQueryResolver::resolve()
 * @see \Switon\Binding\ObjectResolver::resolve()
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RequestQuery extends ResolvedBy
{
    public function __construct()
    {
        parent::__construct(RequestQueryResolver::class);
    }
}
