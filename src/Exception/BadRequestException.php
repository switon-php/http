<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

use Switon\Core\Exception;

/**
 * HTTP 400 — malformed or unusable client request (e.g. invalid JSON body).
 *
 * @see \Switon\Http\Request::parseBody()
 * @see \Switon\Http\DefaultExceptionHandler
 */
class BadRequestException extends Exception
{
    public function getStatusCode(): int
    {
        return 400;
    }
}
