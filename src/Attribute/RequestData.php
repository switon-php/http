<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use Switon\Binding\Attribute\ResolvedBy;
use Switon\Http\RequestDataResolver;

/**
 * Marks a typed input class as resolvable from merged request input.
 *
 * Use when action parameters should bind from merged input (<code>all()</code>).
 *
 * @see \Switon\Http\RequestDataResolver::resolve()
 * @see \Switon\Binding\ObjectResolver::resolve()
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RequestData extends ResolvedBy
{
    public function __construct()
    {
        parent::__construct(RequestDataResolver::class);
    }
}
