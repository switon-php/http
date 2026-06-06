<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

use Switon\Http\Exception;

/**
 * Use when response headers are modified after output has already started.
 *
 * Common causes:
 * - Early output (`echo`/BOM/debug prints) sends body before headers
 * - Header/cookie mutation occurs too late in filter/controller flow
 *
 * Debug/Fix:
 * - Locate first output position and move header mutation before it
 * - Remove unintended output in bootstrap, filter, and view rendering
 *
 * @see \Switon\Http\Exception
 * @see \Switon\Http\Response
 */
class HeadersAlreadySentException extends Exception
{
    public static function at(string $file, int $line): self
    {
        return new self(
            'Headers have already been sent in "{file}" at line {line} - cannot modify headers after output',
            ['file' => $file, 'line' => $line]
        );
    }
}
