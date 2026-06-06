<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted when the server is ready to accept requests.
 *
 * Road-signs:
 * - emitted before the server start confirmation log
 * - carries host, port, and adapter settings
 * - per-request probes may emit this with null server payload
 *
 * Log category: <code>switon.http.server.ready</code>
 *
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\Kernel::start()
 * @see \Switon\Http\Server
 * @see \Switon\Http\ServerOptions
 * @see \Switon\Http\Server\Adapter\Swoole::start()
 */
#[EventLevel(Severity::DEBUG)]
class ServerReady implements JsonSerializable
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public mixed  $server,
        public string $host,
        public int    $port,
        public array  $settings = []
    ) {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        // Per-request probes may reuse this event shape without server startup metadata.
        if ($this->server === null) {
            return [];
        }

        return [
            'host' => $this->host,
            'port' => $this->port,
            'settings' => $this->settings,
        ];
    }
}
