<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * Stores per-request HTTP response state.
 *
 * Stores response state per request/coroutine to ensure isolation in FPM/Swoole environments.
 * Used by Response component to maintain per-request response state.
 *
 * @see \Switon\Http\Response
 * @see \Switon\Http\ResponseInterface
 */
class ResponseContext
{
    /**
     * HTTP status code.
     */
    public int $status_code = 200;

    /**
     * HTTP status text.
     */
    public string $status_text = 'OK';

    /**
     * Response headers.
     *
     * @var array<string, string> Map of header name to header value
     */
    public array $headers = [];

    /**
     * Response cookies.
     *
     * @var array<string, array<string, mixed>> Map of cookie name to cookie configuration
     */
    public array $cookies = [];

    /**
     * Response content.
     */
    public mixed $content = null;

    /**
     * File path for file responses.
     */
    public ?string $file = null;

    /**
     * Whether response uses chunked transfer encoding.
     */
    public bool $chunked = false;
}
