<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

use Switon\Core\Exception;

/**
 * Authentication failure exception mapped to HTTP 401.
 *
 * Guidance: Throw for missing/invalid credentials; use ForbiddenException for permission denied after login.
 *
 * Road-signs:
 * - auth required but not logged in
 * - raised by Authorization::authorize() for guests
 * - mapped to 401 in DefaultExceptionHandler
 * - handled by UnauthorizedHandler redirect/json
 *
 * @see \Switon\Core\Exception
 * @see \Switon\Authorizing\AuthorizationInterface::authorize()
 * @see \Switon\Http\UnauthorizedHandler
 * @see \Switon\Http\Exception\ForbiddenException
 */
class UnauthorizedException extends Exception
{
    public function getStatusCode(): int
    {
        return 401;
    }
}
