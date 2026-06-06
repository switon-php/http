<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted before request validation starts.
 *
 * Log category: <code>switon.http.request.validating</code>
 *
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestValidated
 */
#[EventLevel(Severity::DEBUG)]
class RequestValidating extends AbstractRequestDispatch
{
}
