<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Http\RequestInterface;

/**
 * Event emitted at the start of request handling.
 *
 * Log category: <code>switon.http.request.begin</code>
 *
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestEnd
 * @see \Switon\Http\Filter\RequestLocaleFilter
 */
#[EventLevel(Severity::INFO)]
class RequestBegin implements JsonSerializable
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
            'query' => $this->request->server('QUERY_STRING'),
            'client_ip' => $this->request->ip(),
        ];
    }
}
