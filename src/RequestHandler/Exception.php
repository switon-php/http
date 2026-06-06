<?php

declare(strict_types=1);

namespace Switon\Http\RequestHandler;

/**
 * Base exception for HTTP request handler errors.
 *
 * Use when request dispatch pipeline cannot complete successfully.
 *
 * Common causes:
 * - Route resolution, controller invocation, or lifecycle filter failures
 * - Handler dependencies are missing or misconfigured
 *
 * Debug/Fix:
 * - Check dispatched request lifecycle events and previous exception
 * - Verify handler wiring, route mappings, and filter registration
 *
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Http\RequestHandler
 * @see \Switon\Core\Exception
 */
class Exception extends \Switon\Core\Exception
{
}
