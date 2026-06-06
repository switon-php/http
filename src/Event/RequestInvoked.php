<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use ReflectionMethod;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted after a controller action is invoked.
 *
 * On the HTML view path, {@see RequestRendering} and {@see RequestRendered} run inside
 * {@see \Switon\Http\RequestHandler::invoke()} before this event.
 *
 * Log category: <code>switon.http.request.invoked</code>
 *
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestInvoking
 */
#[EventLevel(Severity::DEBUG)]
class RequestInvoked implements JsonSerializable
{
    public string $controller;
    public string $action;

    public function __construct(
        public ReflectionMethod $method,
        public mixed            $return,
    ) {
        $this->controller = $this->method->getDeclaringClass()->getName();
        $this->action = $this->method->name;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'controller' => $this->controller,
            'action' => $this->action,
        ];
    }
}
