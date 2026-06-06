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
 * Event emitted when an asynchronous task is finished.
 *
 * Log category: <code>switon.http.server.finish</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onFinish()
 * @see \Switon\Http\Server\Event\ServerTask
 */
#[EventLevel(Severity::DEBUG)]
class ServerFinish implements JsonSerializable
{
    public function __construct(public Server $server, public int $task_id, public mixed $data)
    {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $type = is_object($this->data) ? get_class($this->data) : 'data';

        return [
            $type => $this->data,
            'task_id' => $this->task_id,
        ];
    }
}
