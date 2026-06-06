<?php

declare(strict_types=1);

namespace Switon\Http\Server;

use function extension_loaded;

/**
 * Detects the active HTTP server runtime.
 *
 * Road-signs:
 * - `cli` + swoole -> `#swoole`
 * - `cli` or `cli-server` without swoole -> `#php`
 * - other SAPIs -> `#fpm`
 *
 * @see \Switon\Http\Server
 * @see \Switon\Http\ServerOptions::$type
 */
class Detector
{
    /**
     * Detect the server type identifier for `ServerOptions::$type = auto`.
     */
    public static function detect(): string
    {
        if (PHP_SAPI === 'cli') {
            if (extension_loaded('swoole')) {
                return '#swoole';
            } else {
                return '#php';
            }
        } elseif (PHP_SAPI === 'cli-server') {
            return '#php';
        } else {
            return '#fpm';
        }
    }
}
