<?php

declare(strict_types=1);

namespace Switon\Http\Server\Adapter\Native;

use function header;
use function headers_sent;
use function setcookie;

/**
 * Executes HTTP header operations through native PHP functions.
 *
 * @see \Switon\Http\Server\Adapter\Native\HeadersInterface
 * @see \Switon\Http\Server\Adapter\Native\Sender
 */
class Headers implements HeadersInterface
{
    /**
     * {@inheritDoc}
     */
    public function header(string $header, bool $replace = true, ?int $responseCode = null): bool
    {
        if ($responseCode !== null) {
            header($header, $replace, $responseCode);
        } else {
            header($header, $replace);
        }
        // header() returns void in PHP 8.0+, returns true on success (throws exception on failure)
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function headersSent(?string &$file = null, ?int &$line = null): bool
    {
        return headers_sent($file, $line);
    }

    /**
     * {@inheritDoc}
     *
     * @param ''|'Lax'|'lax'|'None'|'none'|'Strict'|'strict' $samesite
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
    ): bool {
        if ($samesite !== '') {

            $options = [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ];
            return setcookie($name, $value, $options);
        }

        return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }
}
