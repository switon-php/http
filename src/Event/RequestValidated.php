<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted after request validation succeeds.
 *
 * Road-signs:
 * - emitted after validators accept the request
 * - controller invocation follows next
 *
 * Log category: <code>switon.http.request.validated</code>
 *
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestValidating
 * @see \Switon\Http\Event\RequestInvoking
 */
#[EventLevel(Severity::DEBUG)]
class RequestValidated extends AbstractRequestDispatch
{
}
