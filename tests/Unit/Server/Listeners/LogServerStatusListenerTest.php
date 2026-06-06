<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Listener;

use Switon\Core\App;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Server\Event\ServerReady;
use Switon\Http\Server\Event\ServerShutdown;
use Switon\Http\Server\Listener\LogServerStatusListener;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LogServerStatusListenerTest extends TestCase
{
    #[Autowired] protected LogServerStatusListener $listener;
    #[Autowired] protected RouterInterface $router;

    protected function beforeSetUpHttpContainer(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->container->replace(RouterInterface::class, $this->router);
        $this->container->replace(App::class, $this->createStub(App::class));
        $this->container->replace(LogServerStatusListener::class, BufferingLogServerStatusListener::class);
    }

    public function testOnServerReadyLogsServerStatus(): void
    {
        $this->router->expects($this->once())
            ->method('getPrefix')
            ->willReturn('/api');

        $event = new ServerReady(null, '127.0.0.1', 8080, ['worker_num' => 4]);
        $this->listener->onServerReady($event);

        $listener = $this->listener;
        $this->assertInstanceOf(BufferingLogServerStatusListener::class, $listener);
        $this->assertCount(2, $listener->writes);
        $this->assertSame('info', $listener->writes[0][0]);
        $this->assertStringContainsString('listen on:', $listener->writes[0][1]);
        $this->assertSame('127.0.0.1', $listener->writes[0][2]['host']);
        $this->assertSame(8080, $listener->writes[0][2]['port']);
        $this->assertSame('info', $listener->writes[1][0]);
        $this->assertStringContainsString('http://', $listener->writes[1][1]);
        $this->assertSame('/api', $listener->writes[1][2]['prefix']);
    }

    public function testOnServerReadyHandlesZeroHost(): void
    {
        $this->router->expects($this->once())
            ->method('getPrefix')
            ->willReturn('');

        $event = new ServerReady(null, '0.0.0.0', 8080);
        $this->listener->onServerReady($event);

        $listener = $this->listener;
        $this->assertInstanceOf(BufferingLogServerStatusListener::class, $listener);
        $this->assertSame('127.0.0.1', $listener->writes[1][2]['host']);
        $this->assertSame('', $listener->writes[1][2]['prefix']);
    }

    public function testOnServerReadyStripsLeadingQuestionMarksFromRouterPrefix(): void
    {
        $this->router->expects($this->once())
            ->method('getPrefix')
            ->willReturn('??/api');

        $event = new ServerReady(null, '127.0.0.1', 8080);
        $this->listener->onServerReady($event);

        $listener = $this->listener;
        $this->assertInstanceOf(BufferingLogServerStatusListener::class, $listener);
        $this->assertSame('/api', $listener->writes[1][2]['prefix']);
    }

    public function testOnServerShutdownLogsShutdownMessage(): void
    {
        if (!class_exists(\Swoole\Http\Server::class)) {
            $this->markTestSkipped('Swoole extension not available');
        }

        $server = $this->createMock(\Swoole\Http\Server::class);
        $event = new ServerShutdown($server);
        $this->listener->onServerShutdown($event);

        $listener = $this->listener;
        $this->assertInstanceOf(BufferingLogServerStatusListener::class, $listener);
        $this->assertCount(1, $listener->writes);
        $this->assertSame('server shutdown', $listener->writes[0][1]);
    }
}

/**
 * Test double: records status lines without writing to STDERR (avoids slow/noisy PHPUnit output).
 *
 * @internal
 */
final class BufferingLogServerStatusListener extends LogServerStatusListener
{
    /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
    public array $writes = [];

    protected function writeStatus(string $level, string $message, array $context = []): void
    {
        $this->writes[] = [$level, $message, $context];
    }
}
