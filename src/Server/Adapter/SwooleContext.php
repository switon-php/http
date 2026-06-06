<?php

declare(strict_types=1);

namespace Switon\Http\Server\Adapter;

use Swoole\Http\Response;

/**
 * Stores per-request Swoole response object for coroutine-safe access.
 *
 * @see \Switon\Http\Server\Adapter\Swoole
 */
class SwooleContext
{
    public Response $response;
}
