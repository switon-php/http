<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\Attribute\Autowired;

/**
 * HTTP server runtime options.
 *
 * Road-signs:
 * - host + port define the listen address
 * - type selects the server adapter
 * - settings pass adapter-specific options through
 *
 * @see \Switon\Http\Server
 * @see \Switon\Http\Kernel::start()
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\Server\Detector
 */
class ServerOptions
{
    /**
     * Listen host.
     */
    #[Autowired] public string $host = '0.0.0.0';

    /**
     * Listen port.
     */
    #[Autowired] public int $port = 9501;

    /**
     * Server adapter type.
     */
    #[Autowired] public string $type = 'auto';

    /**
     * Adapter-specific settings.
     *
     * @var array<string, mixed>
     */
    #[Autowired] public array $settings = [];
}
