<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * HTTP request pipeline entry.
 *
 * Guidance: Boot once at startup; handle() assumes listeners and route mappings are ready, and response-finish listeners should not throw.
 *
 * Road-signs:
 * - startup listener wiring: boot
 * - one request pipeline: handle
 * - route + invoke: RouterInterface / InvokerInterface
 * - failure exit: ExceptionDispatcherInterface
 * - response finish: ResponseAdjust / ResponseStringify / RequestEnd
 * - response-finish listener failures belong to app/server boundary, not RequestHandler normalization
 *
 * @see \Switon\Http\RequestHandler
 * @see \Switon\Http\Kernel
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\ExceptionDispatcherInterface
 * @see \Switon\Routing\RouterInterface
 * @see \Switon\Di\InvokerInterface
 * @see \Switon\Http\Event\RequestBegin
 * @see \Switon\Http\Event\RequestRouting
 * @see \Switon\Http\Event\RequestInvoking
 * @see \Switon\Http\Event\ResponseAdjust
 * @see \Switon\Http\Event\ResponseStringify
 * @see \Switon\Http\Event\RequestEnd
 */
interface RequestHandlerInterface
{
    /**
     * Register configured filters and transformers into the listener provider.
     *
     * Boot-time only; does not process a request.
     */
    public function boot(): void;

    /**
     * Run the current request through routing, action execution, and response finalization.
     *
     * Exceptions from the main request pipeline are dispatched through ExceptionDispatcherInterface.
     * Exceptions thrown by response-finish listeners are extension/app failures and are not normalized here.
     * Request-adjust, routing, authorization, validation, and rendering stages are ordered by handler events.
     */
    public function handle(): void;
}
