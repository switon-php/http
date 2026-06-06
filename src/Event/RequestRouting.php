<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Http\RequestInterface;
use Switon\Routing\RouterInterface;

/**
 * Event emitted before route resolution starts.
 *
 * Log category: <code>switon.http.request.routing</code>
 *
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestRouted
 * @see \Switon\Routing\RouterInterface
 */
#[EventLevel(Severity::DEBUG)]
class RequestRouting implements JsonSerializable
{
    public function __construct(
        public RouterInterface  $router,
        public RequestInterface $request
    ) {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $uri = $this->request->path();

        return [
            'path' => $uri,
        ];
    }
}
