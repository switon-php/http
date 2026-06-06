<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use ReflectionMethod;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;

/**
 * Event emitted after the view-rendering stage completes.
 *
 * Guidance: This marks the end of the HTML render stage; response content may still be empty if no renderer wrote to the response.
 *
 * Road-signs:
 * - emitted after RequestRendering in the same invoke() call
 * - carries the action method, request, and response
 *
 * Log category: <code>switon.http.request.rendered</code>
 *
 * @see \Switon\Http\RequestHandler::invoke()
 * @see \Switon\Http\Event\RequestRendering
 * @see \Switon\Http\ResponseInterface
 */
#[EventLevel(Severity::DEBUG)]
class RequestRendered implements JsonSerializable
{
    /**
     * @param ReflectionMethod $method Action method.
     * @param RequestInterface $request Request instance.
     * @param ResponseInterface $response Response instance.
     */
    public function __construct(
        public ReflectionMethod  $method,
        public RequestInterface  $request,
        public ResponseInterface $response,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'controller' => $this->method->getDeclaringClass()->getName(),
            'action' => $this->method->name,
        ];
    }
}
