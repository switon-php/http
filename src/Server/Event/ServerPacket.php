<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted when the server receives a UDP packet.
 *
 * Log category: <code>switon.http.server.packet</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onPacket()
 */
#[EventLevel(Severity::INFO)]
class ServerPacket implements JsonSerializable
{
    /**
     * @param array<string, mixed> $client
     */
    public function __construct(public Server $server, public string $data, public array $client)
    {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'data_length' => strlen($this->data),
            'client' => $this->client,
        ];
    }
}
