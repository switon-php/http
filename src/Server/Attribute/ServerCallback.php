<?php

declare(strict_types=1);

namespace Switon\Http\Server\Attribute;

use Attribute;

/**
 * Marks a method as a Swoole server lifecycle callback (e.g. onStart, onRequest).
 *
 * @see \Switon\Http\Server\Adapter\Swoole::registerServerCallbacks()
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ServerCallback
{
}
