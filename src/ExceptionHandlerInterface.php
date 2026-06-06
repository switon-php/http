<?php

declare(strict_types=1);

namespace Switon\Http;

use Throwable;

/**
 * HTTP exception response hook.
 *
 * Guidance: Return true only when the handler has decided the HTTP outcome; return false to leave fallback handling in place.
 *
 * Road-signs:
 * - ExceptionDispatcher selects the handler
 * - true means response/redirect is decided
 * - false means caller may continue with fallback handling
 * - DefaultExceptionHandler is the catch-all implementation
 *
 * @see \Switon\Http\ExceptionDispatcherInterface
 * @see \Switon\Http\DefaultExceptionHandler
 * @see \Switon\Http\Event\RequestFailed
 */
interface ExceptionHandlerInterface
{
    /**
     * Handle one exception and report whether this handler claimed it.
     */
    public function handle(Throwable $throwable): bool;
}
