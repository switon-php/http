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
 * Event emitted before a server-side view is rendered.
 *
 * Guidance: This event belongs to the HTML view path only; JSON-preferred requests skip it unless forced by ViewMapping semantics.
 *
 * Road-signs:
 * - emitted from RequestHandler::invoke() before view rendering
 * - carries action return, request, response, and URL prefix
 * - RequestRendered follows after the view result is produced
 *
 * Log category: <code>switon.http.request.rendering</code>
 *
 * @see \Switon\Http\RequestHandler::invoke()
 * @see \Switon\Http\Event\RequestRendered
 * @see \Switon\Http\ResponseInterface
 * @see \Switon\Http\Response\AttachmentRendererInterface
 * @see \Switon\Viewing\ViewRenderer::onRendering()
 * @see \Switon\Viewing\ViewInterface::render()
 * @see \Switon\Viewing\Event\VarsResolving
 * @see \Switon\Viewing\Event\VarsResolved
 */
#[EventLevel(Severity::DEBUG)]
class RequestRendering implements JsonSerializable
{
    /**
     * @param ReflectionMethod $method Action method.
     * @param mixed $return Action return value.
     * @param RequestInterface $request Request instance.
     * @param ResponseInterface $response Response instance.
     * @param string $prefix URL prefix used when fixing links in views.
     */
    public function __construct(
        public ReflectionMethod  $method,
        public mixed             $return,
        public RequestInterface  $request,
        public ResponseInterface $response,
        public string            $prefix = '',
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'controller' => $this->method->getDeclaringClass()->getName(),
            'action' => $this->method->name,
            'prefix' => $this->prefix,
        ];
    }
}
