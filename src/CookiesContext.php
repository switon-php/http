<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * Stores per-request HTTP cookie state.
 *
 * Stores cookie data per request/coroutine to ensure isolation in FPM/Swoole environments.
 * Used by Cookies component to maintain per-request cookie state.
 *
 * @see \Switon\Http\Cookies
 */
class CookiesContext
{
    /**
     * Cookie data array.
     *
     * @var array<string, string> Map of cookie name to cookie value
     */
    public array $cookies = [];
}
