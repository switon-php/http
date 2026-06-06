<?php

declare(strict_types=1);

namespace Switon\Http\Filter;

use Switon\Core\Attribute\Autowired;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\Event\HeadersSending;
use Switon\Http\RequestInterface;

use function sprintf;

/**
 * Appends request execution time to HTTP response headers.
 *
 * Guidance: Use this as a lightweight diagnostics header; durable latency analysis belongs in logs or tracing.
 *
 * Road-signs:
 * - listens on HeadersSending
 * - reads request elapsed time
 * - writes `X-Response-Time` with millisecond precision
 *
 * @see \Switon\Http\Event\HeadersSending
 * @see \Switon\Http\RequestInterface
 */
class AppendResponseTimeFilter
{
    #[Autowired] protected RequestInterface $request;

    #[EventListener] public function onResponseHeadersSending(HeadersSending $event): void
    {
        $event->response->setHeader('X-Response-Time', sprintf('%.3f', $this->request->elapsed()));
    }
}
