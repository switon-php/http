<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Http\RequestInterface;

/**
 * Application hook before request body parsing.
 *
 * Guidance: Use this to rewrite raw request input before parsing; the framework registers no default listeners here.
 *
 * Road-signs:
 * - emitted after RequestReceived
 * - runs before Request::parseBody()
 * - RequestBegin follows next
 *
 * Log category: <code>switon.http.request.adjust</code>
 *
 * @see \Switon\Http\Event\ResponseAdjust
 * @see \Switon\Http\Event\RequestReceived
 * @see \Switon\Http\Event\RequestBegin
 * @see \Switon\Http\RequestHandler::handle()
 */
#[EventLevel(Severity::DEBUG)]
class RequestAdjust implements JsonSerializable
{
    public function __construct(public RequestInterface $request)
    {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'method' => $this->request->verb(),
            'url' => $this->request->url(),
        ];
    }
}
