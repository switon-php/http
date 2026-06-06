<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

use Switon\Core\Exception\InvalidArgumentException;

/**
 * Exception for invalid indexed array parameter.
 *
 * Thrown when {@see \Switon\Http\Request::filters()} receives a non-indexed `$fields` array.
 */
class InvalidIndexedArrayException extends InvalidArgumentException
{
}
