<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

use function get_class;
use function is_object;

/**
 * Event emitted when a worker receives a pipe message.
 *
 * Log category: <code>switon.http.server.pipe.message</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onPipeMessage()
 */
#[EventLevel(Severity::INFO)]
class ServerPipeMessage implements JsonSerializable
{
    public function __construct(public Server $server, public int $src_worker_id, public mixed $message)
    {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $type = is_object($this->message) ? get_class($this->message) : 'message';

        return [
            $type => $this->message,
            'src_worker_id' => $this->src_worker_id,
        ];
    }
}
