<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

use Switon\Core\Exception;

/**
 * Authorization failure exception mapped to HTTP 403.
 *
 * Guidance: Throw when authenticated user lacks permission; use UnauthorizedException for missing/invalid credentials.
 *
 * Road-signs:
 * - auth ok but forbidden
 * - raised by Authorization::authorize() for logged-in users
 * - mapped to 403 in DefaultExceptionHandler
 * - handled by ForbiddenHandler json/text
 *
 * @see \Switon\Core\Exception
 * @see \Switon\Authorizing\AuthorizationInterface::authorize()
 * @see \Switon\Http\ForbiddenHandler
 * @see \Switon\Http\Exception\UnauthorizedException
 */
class ForbiddenException extends Exception
{
    public function getStatusCode(): int
    {
        return 403;
    }
}
