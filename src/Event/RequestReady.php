<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted when request handling is ready to start.
 *
 * Road-signs:
 * - emitted after request normalization is complete
 * - authorization follows next in the main pipeline
 *
 * Log category: <code>switon.http.request.ready</code>
 *
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestAuthorizing
 */
#[EventLevel(Severity::DEBUG)]
class RequestReady extends AbstractRequestDispatch
{
}
