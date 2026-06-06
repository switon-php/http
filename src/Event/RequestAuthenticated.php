<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Http\RequestInterface;

/**
 * Event emitted after request authentication succeeds.
 *
 * Road-signs:
 * - identity is available on the request context now
 * - routing continues after authentication passes
 *
 * Log category: <code>switon.http.request.authenticated</code>
 *
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestAuthenticating
 * @see \Switon\Http\Event\RequestRouting
 * @see \Switon\Principal\IdentityInterface
 */
#[EventLevel(Severity::DEBUG)]
class RequestAuthenticated implements JsonSerializable
{
    public function __construct(public RequestInterface $request)
    {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [];
    }
}
