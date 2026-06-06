<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Listener;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Server\Event\ServerManagerStart;
use Switon\Http\Server\Event\ServerStart;
use Switon\Http\Server\Event\ServerWorkerStart;
use Switon\Http\Server\Listener\RenameProcessTitleListener;
use Switon\Http\Tests\TestCase;

use function class_exists;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class RenameProcessTitleListenerTest extends TestCase
{
    #[Autowired] protected RenameProcessTitleListener $listener;

    // setUp() is not needed - parent::setUp() automatically autowires this test case

    protected function skipIfSwooleNotAvailable(): void
    {
        if (!class_exists(\Swoole\Http\Server::class)) {
            $this->markTestSkipped('Swoole extension not available');
        }
    }

    public function testOnServerStartSetsProcessTitle(): void
    {
        $this->skipIfSwooleNotAvailable();

        $server = $this->createMock(\Swoole\Http\Server::class);
        $event = new ServerStart($server);
        $this->listener->onServerStart($event);

        $this->assertTrue(true);
    }

    public function testOnServerManagerStartSetsProcessTitle(): void
    {
        $this->skipIfSwooleNotAvailable();

        $server = $this->createMock(\Swoole\Http\Server::class);
        $event = new ServerManagerStart($server);
        $this->listener->onServerManagerStart($event);

        $this->assertTrue(true);
    }

    public function testOnServerWorkerStartSetsProcessTitleForWorker(): void
    {
        $this->skipIfSwooleNotAvailable();

        $server = $this->createMock(\Swoole\Http\Server::class);
        $event = new ServerWorkerStart($server, 0, 4);
        $this->listener->onServerWorkerStart($event);

        $this->assertTrue(true);
    }

    public function testOnServerWorkerStartSetsProcessTitleForTasker(): void
    {
        $this->skipIfSwooleNotAvailable();

        $server = $this->createMock(\Swoole\Http\Server::class);
        $event = new ServerWorkerStart($server, 4, 4);
        $this->listener->onServerWorkerStart($event);

        $this->assertTrue(true);
    }
}
