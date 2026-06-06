<?php

declare(strict_types=1);

namespace Switon\Http;

use Throwable;

/**
 * HTTP exception-to-handler dispatcher.
 *
 * Guidance: Register specific exception handlers first; keep Throwable as the final fallback.
 *
 * Road-signs:
 * - resolve order: exact class -> parent -> interface -> Throwable
 * - handler may redirect or write response
 * - StopFlow from handler is swallowed here
 * - RequestHandler dispatches failures here
 *
 * @see \Switon\Http\ExceptionDispatcher
 * @see \Switon\Http\ExceptionHandlerInterface
 * @see \Switon\Http\DefaultExceptionHandler
 * @see \Switon\Core\StopFlow
 * @see \Switon\Core\NotFoundInterface
 * @see \Switon\Http\Event\RequestFailed
 */
interface ExceptionDispatcherInterface
{
    /**
     * Resolve the mapped handler for an exception and invoke it.
     *
     * Implementations should stop on the first handler that claims the exception.
     */
    public function dispatch(Throwable $exception): void;
}
