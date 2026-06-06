<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Event;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Switon\Http\Server\Event\ServerManagerStart;
use Switon\Http\Server\Event\ServerShutdown;
use Switon\Http\Server\Event\ServerStarted;
use Switon\Http\Server\Event\ServerWorkerStart;
use Switon\Http\Tests\TestCase;
use Swoole\Http\Server;

/**
 * JsonSerializable payloads for Swoole-scoped server events.
 */
#[AllowMockObjectsWithoutExpectations]
#[RequiresPhpExtension('swoole')]
class SwooleServerEventSerializationTest extends TestCase
{
    public function testServerManagerStartJsonSerializeExposesManagerPid(): void
    {
        $server = $this->createMock(Server::class);
        $server->manager_pid = 4242;

        $event = new ServerManagerStart($server);
        $this->assertSame(['manager_pid' => 4242], $event->jsonSerialize());
    }

    public function testServerWorkerStartJsonSerializeExposesWorkerFields(): void
    {
        $server = $this->createMock(Server::class);
        $event = new ServerWorkerStart($server, 2, 8);

        $this->assertSame(['worker_id' => 2, 'worker_num' => 8], $event->jsonSerialize());
    }

    public function testServerShutdownJsonSerializeExposesMasterPid(): void
    {
        $server = $this->createMock(Server::class);
        $server->master_pid = 9001;

        $event = new ServerShutdown($server);
        $this->assertSame(['master_pid' => 9001], $event->jsonSerialize());
    }

    public function testServerStartedJsonSerializeOmitsServerObject(): void
    {
        $server = $this->createMock(Server::class);
        $event = new ServerStarted(
            'ready',
            $server,
            0.12,
            '10.0.0.1',
            9501,
            'testing',
            '8.3.0',
            '5.1.0',
            4,
            555
        );

        $data = $event->jsonSerialize();
        $this->assertSame([
            'tip' => 'ready',
            'elapsed' => 0.12,
            'host' => '10.0.0.1',
            'port' => 9501,
            'env' => 'testing',
            'php_version' => '8.3.0',
            'swoole_version' => '5.1.0',
            'workers' => 4,
            'pid' => 555,
        ], $data);
        $this->assertArrayNotHasKey('server', $data);
    }
}
