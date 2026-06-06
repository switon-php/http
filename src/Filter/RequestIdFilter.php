<?php

declare(strict_types=1);

namespace Switon\Http\Filter;

use Switon\Core\Attribute\Autowired;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\Event\HeadersSending;
use Switon\Http\Event\RequestBegin;
use Switon\Http\RequestInterface;
use Switon\Id\IdGeneratorInterface;

/**
 * Injects a request ID into request context and response headers.
 *
 * Guidance: Let upstream proxies keep their existing request IDs; this filter only fills the gap when the header is missing.
 *
 * Road-signs:
 * - RequestBegin ensures one request ID exists
 * - HeadersSending mirrors it into the response
 * - request header stays the source of truth
 *
 * @see \Switon\Id\IdGeneratorInterface
 * @see \Switon\Http\Event\RequestBegin
 * @see \Switon\Http\Event\HeadersSending
 * @see \Switon\Http\RequestInterface
 */
class RequestIdFilter
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected IdGeneratorInterface $uuid4;

    /**
     * Ensure a request ID exists before request handling proceeds.
     */
    /** @noinspection PhpUnusedParameterInspection */
    #[EventListener] public function onBegin(RequestBegin $event): void
    {
        if ($this->request->header('x-request-id') === null) {
            $this->request->getContext()->headers['x-request-id'] = $this->uuid4->next();
        }
    }

    /**
     * Mirror the request ID into response headers.
     */
    #[EventListener] public function onResponseHeadersSending(HeadersSending $event): void
    {
        if (($requestId = $this->request->header('x-request-id')) !== null) {
            $event->response->setHeader('X-Request-Id', $requestId);
        }
    }
}
