<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted before request authorization starts.
 *
 * Road-signs:
 * - emitted after route resolution and authentication
 * - forbidden failures stop the dispatch path
 * - next hop is RequestAuthorized on success
 *
 * Log category: <code>switon.http.request.authorizing</code>
 *
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestAuthorized
 * @see \Switon\Http\Exception\ForbiddenException
 * @see \Switon\Authorizing\Authorization::onAuthorizing()
 */
#[EventLevel(Severity::DEBUG)]
class RequestAuthorizing extends AbstractRequestDispatch
{
}
