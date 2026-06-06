<?php

declare(strict_types=1);

namespace Switon\Http\Request\File;

/**
 * Use when uploaded file payload cannot be accepted as a valid request file.
 *
 * Common causes:
 * - Upload error code, missing temporary file, or invalid file metadata
 * - File exceeds server/application upload constraints
 *
 * Debug/Fix:
 * - Check PHP upload limits and client multipart payload
 * - Verify temporary directory permissions and file metadata validation
 *
 * @see \Switon\Http\Request\Exception
 * @see \Switon\Http\Exception
 */
class Exception extends \Switon\Http\Exception
{
    public function getStatusCode(): int
    {
        return 400;
    }
}
