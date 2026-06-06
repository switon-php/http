<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use ReflectionMethod;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted after a controller instance is resolved.
 *
 * Road-signs:
 * - emitted after the controller object is resolved from the container
 * - listeners may decorate or initialize the controller instance
 * - paired with the action ReflectionMethod
 *
 * Log category: <code>switon.http.controller.resolved</code>
 *
 * @see \Switon\Http\RequestHandler::invoke()
 * @see \Switon\Invoking\InvokerInterface
 */
#[EventLevel(Severity::DEBUG)]
class ControllerResolved implements JsonSerializable
{
    /**
     * @param object $controller Resolved controller instance.
     * @param ReflectionMethod $method Action method that will be invoked next.
     */
    public function __construct(
        public object           $controller,
        public ReflectionMethod $method
    ) {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'controller' => $this->controller::class,
            'method' => $this->method->name,
        ];
    }
}
