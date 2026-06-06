<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

use JetBrains\PhpStorm\ArrayShape;
use Switon\Core\Exception;

/**
 * Use when request rate exceeds configured limiter thresholds.
 *
 * Common causes:
 * - Client sends requests above quota within the current time window
 * - Shared limiter keys aggregate traffic from multiple callers
 *
 * Debug/Fix:
 * - Check limiter key strategy and threshold/window configuration
 * - Honor retry/backoff behavior on caller side
 *
 * @see \Switon\Core\Exception
 * @see \Switon\Throttle\RateLimiterInterface::hit()
 */
class TooManyRequestsException extends Exception
{
    public function getStatusCode(): int
    {
        /**
         * https://tools.ietf.org/html/rfc6585#section-4
         */
        return 429;
    }

    #[ArrayShape(['code' => 'int', 'msg' => 'string'])]
    public function getJson(): array
    {
        return ['code' => 429, 'msg' => 'Too Many Requests'];
    }
}
