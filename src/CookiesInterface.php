<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * HTTP cookie read/write boundary.
 *
 * Guidance: Read current request cookies here and queue response cookies here; use RequestInterface for non-cookie input.
 *
 * Road-signs:
 * - read current request cookies: all / get / has
 * - queue response mutation: set / delete
 * - actual emission happens at the response sender boundary
 * - Session and locale filters are typical consumers
 *
 * @see \Switon\Http\Cookies
 * @see \Switon\Session\AbstractSession
 * @see \Switon\Http\Filter\RequestLocaleFilter
 * @see \Switon\Http\RequestHandler
 * @see \Switon\Http\ResponseInterface
 */
interface CookiesInterface
{
    /**
     * @return array<string, string>
     * Return current request cookies.
     */
    public function all(): array;

    /**
     * Queue a response cookie.
     */
    public function set(
        string  $name,
        string  $value,
        int     $expire = 0,
        ?string $path = null,
        ?string $domain = null,
        bool    $secure = false,
        bool    $httponly = true
    ): static;

    /**
     * Read one request cookie.
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Check request cookie presence.
     */
    public function has(string $name): bool;

    /**
     * Queue cookie deletion in the response.
     */
    public function delete(string $name, ?string $path = null, ?string $domain = null): static;
}
