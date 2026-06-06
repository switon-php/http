<?php

declare(strict_types=1);

namespace Switon\Http\Server\Adapter\Native;

/**
 * Native PHP header/cookie output boundary.
 *
 * Guidance: Use this only inside native sender implementations; higher layers should mutate ResponseInterface instead.
 *
 * Road-signs:
 * - header() forwards one raw header line
 * - headersSent() exposes native sent-state and first output location
 * - setcookie() forwards native cookie emission
 *
 * @see \Switon\Http\Server\Adapter\Native\Headers For the default implementation
 */
interface HeadersInterface
{
    /**
     * Send one raw header line.
     */
    public function header(string $header, bool $replace = true, ?int $responseCode = null): bool;

    /**
     * Check native header sent-state and first output location.
     */
    public function headersSent(?string &$file = null, ?int &$line = null): bool;

    /**
     * Send one cookie via the native runtime.
     */
    public function setcookie(
        string $name,
        string $value = '',
        int    $expire = 0,
        string $path = '',
        string $domain = '',
        bool   $secure = false,
        bool   $httponly = false,
        string $samesite = ''
    ): bool;
}
