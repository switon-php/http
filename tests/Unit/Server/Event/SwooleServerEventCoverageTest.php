<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Event;

use Closure;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Switon\Http\Server\Event\ServerBeforeShutdown;
use Switon\Http\Server\Event\ServerClose;
use Switon\Http\Server\Event\ServerConnect;
use Switon\Http\Server\Event\ServerDispatchedTask;
use Switon\Http\Server\Event\ServerFinish;
use Switon\Http\Server\Event\ServerManagerStart;
use Switon\Http\Server\Event\ServerManagerStop;
use Switon\Http\Server\Event\ServerPacket;
use Switon\Http\Server\Event\ServerPipeMessage;
use Switon\Http\Server\Event\ServerShutdown;
use Switon\Http\Server\Event\ServerTask;
use Switon\Http\Server\Event\ServerTaskerError;
use Switon\Http\Server\Event\ServerTaskerExit;
use Switon\Http\Server\Event\ServerTaskerStart;
use Switon\Http\Server\Event\ServerTaskerStop;
use Switon\Http\Server\Event\ServerWorkerError;
use Switon\Http\Server\Event\ServerWorkerExit;
use Switon\Http\Server\Event\ServerWorkerStop;
use Switon\Http\Tests\TestCase;
use Swoole\Http\Server;
use stdClass;

#[AllowMockObjectsWithoutExpectations]
#[RequiresPhpExtension('swoole')]
final class SwooleServerEventCoverageTest extends TestCase
{
    #[DataProvider('serializationCases')]
    public function testJsonSerializeForServerEvents(Closure $factory, array $expected): void
    {
        $server = $this->createMock(Server::class);
        $event = $factory($server);

        $this->assertSame($expected, $event->jsonSerialize());
    }

    public static function serializationCases(): array
    {
        $taskPayload = new stdClass();
        $taskPayload->kind = 'task';

        $finishPayload = new stdClass();
        $finishPayload->kind = 'finish';

        $pipePayload = new stdClass();
        $pipePayload->kind = 'pipe';

        return [
            'manager start' => [
                static function (Server $server): object {
                    $server->manager_pid = 4242;

                    return new ServerManagerStart($server);
                },
                ['manager_pid' => 4242],
            ],
            'manager stop' => [
                static function (Server $server): object {
                    $server->manager_pid = 4343;

                    return new ServerManagerStop($server);
                },
                ['manager_pid' => 4343],
            ],
            'before shutdown' => [
                static function (Server $server): object {
                    $server->master_pid = 5151;

                    return new ServerBeforeShutdown($server);
                },
                ['master_pid' => 5151],
            ],
            'shutdown' => [
                static function (Server $server): object {
                    $server->master_pid = 5252;

                    return new ServerShutdown($server);
                },
                ['master_pid' => 5252],
            ],
            'close' => [
                static function (Server $server): object {
                    return new ServerClose($server, 12, 3);
                },
                ['fd' => 12, 'reactor_id' => 3],
            ],
            'connect' => [
                static function (Server $server): object {
                    return new ServerConnect($server, 21, 4);
                },
                ['fd' => 21, 'reactor_id' => 4],
            ],
            'dispatched task string payload' => [
                static function (Server $server): object {
                    return new ServerDispatchedTask($server, 7, 2, 'payload');
                },
                ['data' => 'payload', 'task_id' => 7, 'src_worker_id' => 2],
            ],
            'finish object payload' => [
                static function (Server $server) use ($finishPayload): object {
                    return new ServerFinish($server, 9, $finishPayload);
                },
                ['stdClass' => $finishPayload, 'task_id' => 9],
            ],
            'packet' => [
                static function (Server $server): object {
                    return new ServerPacket($server, 'abc', ['address' => '127.0.0.1']);
                },
                ['data_length' => 3, 'client' => ['address' => '127.0.0.1']],
            ],
            'pipe message object payload' => [
                static function (Server $server) use ($pipePayload): object {
                    return new ServerPipeMessage($server, 5, $pipePayload);
                },
                ['stdClass' => $pipePayload, 'src_worker_id' => 5],
            ],
            'task object payload' => [
                static function (Server $server) use ($taskPayload): object {
                    return new ServerTask($server, 11, 6, $taskPayload);
                },
                ['stdClass' => $taskPayload, 'task_id' => 11, 'src_worker_id' => 6],
            ],
            'tasker error' => [
                static function (Server $server): object {
                    return new ServerTaskerError($server, 8, 13, 555, 1, 9);
                },
                ['worker_id' => 8, 'task_id' => 13, 'worker_pid' => 555, 'exit_code' => 1, 'signal' => 9],
            ],
            'tasker exit' => [
                static function (Server $server): object {
                    return new ServerTaskerExit($server, 14, 3);
                },
                ['worker_id' => 14, 'tasker_id' => 3],
            ],
            'tasker start' => [
                static function (Server $server): object {
                    return new ServerTaskerStart($server, 15, 4);
                },
                ['worker_id' => 15, 'tasker_id' => 4],
            ],
            'tasker stop' => [
                static function (Server $server): object {
                    return new ServerTaskerStop($server, 16, 5);
                },
                ['worker_id' => 16, 'tasker_id' => 5],
            ],
            'worker error' => [
                static function (Server $server): object {
                    return new ServerWorkerError($server, 17, 18, 19, 20);
                },
                ['worker_id' => 17, 'worker_pid' => 18, 'exit_code' => 19, 'signal' => 20],
            ],
            'worker exit' => [
                static function (Server $server): object {
                    return new ServerWorkerExit($server, 22, 7);
                },
                ['worker_id' => 22, 'worker_num' => 7],
            ],
            'worker stop' => [
                static function (Server $server): object {
                    return new ServerWorkerStop($server, 23, 8);
                },
                ['worker_id' => 23, 'worker_num' => 8],
            ],
        ];
    }
}
