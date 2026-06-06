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
 * Optional event for application-level logging when work is dispatched to a task worker.
 *
 * The server adapter emits {@see ServerTask} from {@see \Switon\Http\Server\Adapter\Swoole::onTask()} on the task
 * worker; this class is not dispatched by the framework.
 *
 * Log category: <code>switon.http.server.dispatched.task</code>
 *
 * @see \Switon\Http\Server\Event\ServerTask Adapter-emitted task event
 */
#[EventLevel(Severity::DEBUG)]
class ServerDispatchedTask implements JsonSerializable
{
    public function __construct(
        public Server $server,
        public int    $task_id,
        public int    $src_worker_id,
        public mixed  $data
    ) {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $type = is_object($this->data) ? get_class($this->data) : 'data';

        return [
            $type => $this->data,
            'task_id' => $this->task_id,
            'src_worker_id' => $this->src_worker_id,
        ];
    }
}
