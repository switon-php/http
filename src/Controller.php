<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * Optional marker for attribute-routed HTTP controller classes.
 *
 * Guidance: Use for HTTP action classes when you want a stable entry marker; routing attributes and RequestHandler still drive dispatch.
 *
 * Road-signs:
 * - class RequestMapping sets controller prefix
 * - method `*Mapping` selects verbs
 * - RouterInterface match resolves handler
 * - InvokerInterface invoke calls action
 * - ExceptionDispatcherInterface normalizes failures
 *
 * @see \Switon\Http\RequestHandler
 * @see \Switon\Routing\Attribute\RequestMapping
 * @see \Switon\Routing\Attribute\Mapping
 * @see \Switon\Routing\RouterInterface::match()
 * @see \Switon\Invoking\InvokerInterface::invoke()
 * @see \Switon\Http\ExceptionDispatcherInterface
 * @see \Switon\Http\DefaultExceptionHandler
 */
class Controller
{
}
