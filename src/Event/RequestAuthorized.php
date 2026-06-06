<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted after request authorization succeeds.
 *
 * Log category: <code>switon.http.request.authorized</code>
 *
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestAuthorizing
 * @see \Switon\Http\Event\RequestValidating
 */
#[EventLevel(Severity::DEBUG)]
class RequestAuthorized extends AbstractRequestDispatch
{
}
