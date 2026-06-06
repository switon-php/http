<?php

declare(strict_types=1);

namespace Switon\Http\Request;

/**
 * Base exception for HTTP request errors.
 *
 * Use when request normalization, parsing, or validation cannot complete.
 *
 * Common causes:
 * - Malformed request payload, headers, or query parameters
 * - Request data violates validation or expected structure
 *
 * Debug/Fix:
 * - Inspect raw request data and parsed request context
 * - Confirm request schema, validators, and filter assumptions
 *
 * @see \Switon\Http\Request\File\Exception
 * @see \Switon\Http\Exception
 */
class Exception extends \Switon\Http\Exception
{
}
