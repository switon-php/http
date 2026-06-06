<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted before controller action invocation.
 *
 * Log category: <code>switon.http.request.invoking</code>
 *
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Invoking\InvokerInterface Action invocation
 * @see \Switon\Http\Event\RequestInvoked
 */
#[EventLevel(Severity::DEBUG)]
class RequestInvoking extends AbstractRequestDispatch
{
}
