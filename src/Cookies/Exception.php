<?php

declare(strict_types=1);

namespace Switon\Http\Cookies;

/**
 * Base exception for HTTP cookie errors.
 *
 * Use when cookie parsing, serialization, or emission fails in HTTP responses.
 *
 * Common causes:
 * - Invalid cookie options (name/path/domain/expiry) or unsupported values
 * - Cookie write attempts after headers are already sent
 *
 * Debug/Fix:
 * - Validate cookie payload and options before adding to response
 * - Ensure cookies are written before body output starts
 *
 * @see \Switon\Http\Cookies
 * @see \Switon\Http\Exception
 */
class Exception extends \Switon\Http\Exception
{
}
