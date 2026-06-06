<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Adapter;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Http\Event\AssetSending;
use Switon\Http\Event\AssetSent;
use Switon\Http\Event\RequestReceived;
use Switon\Http\RequestHandlerInterface;
use Switon\Http\Server\Adapter\Native\SenderInterface;
use Switon\Http\Server\Adapter\Php;
use Switon\Http\Server\Event\ServerReady;
use Switon\Http\Server\StaticHandlerInterface;
use Switon\Http\ServerOptions;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class PhpTest extends TestCase
{
    /**
     * Test PHP adapter constructor initializes server variables correctly.
     */
    public function testConstructorInitializesServerVariables(): void
    {
        // Arrange
        $app = $this->createMock(App::class);
        $serverOptions = new ServerOptions();
        $serverOptions->host = '127.0.0.1';
        $serverOptions->port = 8080;

        $this->container->set(ServerOptions::class, $serverOptions);
        $this->container->set(AppInterface::class, $app);

        // Act
        $adapter = $this->container->make(Php::class);

        // Assert
        $this->assertEquals('http', $_SERVER['REQUEST_SCHEME']);
        $this->assertArrayHasKey('SERVER_ADDR', $_SERVER);
        $this->assertArrayHasKey('SERVER_PORT', $_SERVER);
    }

    /**
     * Test constructor handles port argument from command line.
     */
    public function testConstructorHandlesPortArgument(): void
    {
        // Arrange
        $app = $this->createMock(App::class);
        $serverOptions = new ServerOptions();
        $serverOptions->host = '127.0.0.1';
        $serverOptions->port = 8080;

        $this->container->set(ServerOptions::class, $serverOptions);
        $this->container->set(AppInterface::class, $app);

        $originalArgv = $GLOBALS['argv'] ?? [];
        $GLOBALS['argv'] = ['script.php', '--port', '9000'];

        // Act
        $adapter = $this->container->make(Php::class);

        // Assert - verify through public $_SERVER variable set by constructor
        $this->assertEquals(9000, $_SERVER['SERVER_PORT']);

        // Cleanup
        $GLOBALS['argv'] = $originalArgv;
    }

    /**
     * Test sendHeaders method delegates to sender.
     */
    public function testSendHeadersDelegatesToSender(): void
    {
        // Arrange
        $app = $this->createMock(App::class);
        $sender = $this->createMock(SenderInterface::class);
        $serverOptions = new ServerOptions();
        $serverOptions->host = '127.0.0.1';
        $serverOptions->port = 8080;

        $this->container->set(AppInterface::class, $app);
        $this->container->set(ServerOptions::class, $serverOptions);
        $this->container->set(SenderInterface::class, $sender);

        $adapter = $this->container->make(Php::class);

        $sender->expects($this->once())
            ->method('sendHeaders');

        // Act
        $adapter->sendHeaders();

        // Assert - test passes if expectations are met
        $this->assertTrue(true);
    }

    /**
     * Test sendBody method delegates to sender.
     */
    public function testSendBodyDelegatesToSender(): void
    {
        // Arrange
        $app = $this->createMock(App::class);
        $sender = $this->createMock(SenderInterface::class);
        $serverOptions = new ServerOptions();
        $serverOptions->host = '127.0.0.1';
        $serverOptions->port = 8080;

        $this->container->set(AppInterface::class, $app);
        $this->container->set(ServerOptions::class, $serverOptions);
        $this->container->set(SenderInterface::class, $sender);

        $adapter = $this->container->make(Php::class);

        $sender->expects($this->once())
            ->method('sendBody');

        // Act
        $adapter->sendBody();

        // Assert - test passes if expectations are met
        $this->assertTrue(true);
    }

    public function testHandleStaticRequestDispatchesAssetEventsAndSendsFileWhenFileExists(): void
    {
        $uri = '/static/style.css';

        // Arrange: minimal adapter dependencies
        $app = $this->createMock(App::class);
        $serverOptions = new ServerOptions();
        $serverOptions->host = '127.0.0.1';
        $serverOptions->port = 8080;
        $this->container->set(ServerOptions::class, $serverOptions);
        $this->container->set(AppInterface::class, $app);

        $staticHandler = $this->createMock(StaticHandlerInterface::class);
        $staticHandler->expects($this->once())
            ->method('isFile')
            ->with($uri)
            ->willReturn(true);
        $staticHandler->expects($this->once())
            ->method('getFile')
            ->with($uri)
            ->willReturn('/path/to/style.css');
        $staticHandler->expects($this->once())
            ->method('getMimeType')
            ->with('/path/to/style.css')
            ->willReturn('text/css');
        $this->container->replace(StaticHandlerInterface::class, $staticHandler);

        $events = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });
        $this->container->replace(EventDispatcherInterface::class, $eventDispatcher);

        // Act
        $adapter = $this->container->make(TestablePhpAdapter::class);
        $result = $adapter->callHandleStaticRequest($uri);

        // Assert
        $this->assertTrue($result);
        $this->assertSame(['Content-Type: text/css'], $adapter->capturedHeaders);
        $this->assertSame(['/path/to/style.css'], $adapter->readFiles);

        $sending = array_values(array_filter($events, static fn (object $e): bool => $e instanceof AssetSending));
        $sent = array_values(array_filter($events, static fn (object $e): bool => $e instanceof AssetSent));
        $this->assertCount(1, $sending);
        $this->assertCount(1, $sent);
        $this->assertSame($uri, $sending[0]->uri);
        $this->assertSame($uri, $sent[0]->uri);
        $this->assertSame(200, $sent[0]->statusCode);
    }

    public function testHandleStaticRequestDispatches404WhenFileMissing(): void
    {
        $uri = '/static/missing.css';

        // Arrange: minimal adapter dependencies
        $app = $this->createMock(App::class);
        $serverOptions = new ServerOptions();
        $serverOptions->host = '127.0.0.1';
        $serverOptions->port = 8080;
        $this->container->set(ServerOptions::class, $serverOptions);
        $this->container->set(AppInterface::class, $app);

        $staticHandler = $this->createMock(StaticHandlerInterface::class);
        $staticHandler->expects($this->once())
            ->method('isFile')
            ->with($uri)
            ->willReturn(true);
        $staticHandler->expects($this->once())
            ->method('getFile')
            ->with($uri)
            ->willReturn(null);
        $this->container->replace(StaticHandlerInterface::class, $staticHandler);

        $events = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$events): object {
                $events[] = $event;
                return $event;
            });
        $this->container->replace(EventDispatcherInterface::class, $eventDispatcher);

        // Act
        $adapter = $this->container->make(TestablePhpAdapter::class);
        $result = $adapter->callHandleStaticRequest($uri);

        // Assert
        $this->assertTrue($result);
        $this->assertSame(['HTTP/1.1 404 Not Found'], $adapter->capturedHeaders);
        $this->assertSame([], $adapter->readFiles);

        $sent = array_values(array_filter($events, static fn (object $e): bool => $e instanceof AssetSent));
        $this->assertCount(1, $sent);
        $this->assertSame($uri, $sent[0]->uri);
        $this->assertSame(404, $sent[0]->statusCode);
    }

    public function testPrepareGlobalsDispatchesRequestReceivedWithSuperglobals(): void
    {
        $saved = [
            '_GET' => $_GET,
            '_POST' => $_POST,
            '_SERVER' => $_SERVER,
            '_COOKIE' => $_COOKIE,
            '_FILES' => $_FILES,
        ];

        $_GET = ['q' => 'ok'];
        $_POST = ['body' => '1'];
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api', 'QUERY_STRING' => 'q=ok'];
        $_COOKIE = ['sid' => 'x'];
        $_FILES = [];

        try {
            $app = $this->createMock(App::class);
            $serverOptions = new ServerOptions();
            $serverOptions->host = '127.0.0.1';
            $serverOptions->port = 8080;
            $this->container->set(ServerOptions::class, $serverOptions);
            $this->container->set(AppInterface::class, $app);

            $captured = null;
            $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
            $eventDispatcher->expects($this->atLeastOnce())
                ->method('dispatch')
                ->willReturnCallback(static function (object $event) use (&$captured): object {
                    if ($event instanceof RequestReceived) {
                        $captured = $event;
                    }

                    return $event;
                });
            $this->container->replace(EventDispatcherInterface::class, $eventDispatcher);

            $adapter = $this->container->make(TestablePhpAdapter::class);
            $adapter->callPrepareGlobals();
        } finally {
            $_GET = $saved['_GET'];
            $_POST = $saved['_POST'];
            $_SERVER = $saved['_SERVER'];
            $_COOKIE = $saved['_COOKIE'];
            $_FILES = $saved['_FILES'];
        }

        $this->assertInstanceOf(RequestReceived::class, $captured);
        $this->assertSame(['q' => 'ok'], $captured->GET);
        $this->assertSame(['body' => '1'], $captured->POST);
        $this->assertSame('POST', $captured->SERVER['REQUEST_METHOD'] ?? null);
        $this->assertSame('/api', $captured->SERVER['REQUEST_URI'] ?? null);
        $this->assertSame('q=ok', $captured->SERVER['QUERY_STRING'] ?? null);
        $this->assertSame(8080, $captured->SERVER['SERVER_PORT'] ?? null);
        $this->assertTrue($captured->RAW_BODY === null || $captured->RAW_BODY === '');
        $this->assertSame(['sid' => 'x'], $captured->COOKIE);
        $this->assertSame([], $captured->FILES);
    }

    public function testConstructorResolvesPortFromShortFlag(): void
    {
        $app = $this->createMock(App::class);
        $serverOptions = new ServerOptions();
        $serverOptions->host = '127.0.0.1';
        $serverOptions->port = 8080;

        $this->container->set(ServerOptions::class, $serverOptions);
        $this->container->set(AppInterface::class, $app);

        $originalArgv = $GLOBALS['argv'] ?? [];
        $GLOBALS['argv'] = ['script.php', '-p', '7777'];

        try {
            $this->container->make(Php::class);
            $this->assertSame(7777, $_SERVER['SERVER_PORT']);
        } finally {
            $GLOBALS['argv'] = $originalArgv;
        }
    }

    public function testHandleStaticRequestReturnsFalseWhenUriIsNotAStaticFile(): void
    {
        $uri = '/app/route';

        $app = $this->createMock(App::class);
        $serverOptions = new ServerOptions();
        $serverOptions->host = '127.0.0.1';
        $serverOptions->port = 8080;
        $this->container->set(ServerOptions::class, $serverOptions);
        $this->container->set(AppInterface::class, $app);

        $staticHandler = $this->createMock(StaticHandlerInterface::class);
        $staticHandler->expects($this->once())->method('isFile')->with($uri)->willReturn(false);
        $staticHandler->expects($this->never())->method('getFile');
        $this->container->replace(StaticHandlerInterface::class, $staticHandler);

        $adapter = $this->container->make(TestablePhpAdapter::class);
        $this->assertFalse($adapter->callHandleStaticRequest($uri));
    }

    public function testStartDispatchesServerReadyAndInvokesRequestHandlerInNonCliMode(): void
    {
        $originalUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/dynamic';

        try {
            $app = $this->createMock(App::class);
            $serverOptions = new ServerOptions();
            $serverOptions->host = '127.0.0.1';
            $serverOptions->port = 8080;
            $this->container->set(ServerOptions::class, $serverOptions);
            $this->container->set(AppInterface::class, $app);

            $handler = $this->createMock(RequestHandlerInterface::class);
            $handler->expects($this->once())->method('handle');
            $this->container->replace(RequestHandlerInterface::class, $handler);

            $events = [];
            $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
            $eventDispatcher->expects($this->atLeastOnce())
                ->method('dispatch')
                ->willReturnCallback(static function (object $e) use (&$events): object {
                    $events[] = $e;

                    return $e;
                });
            $this->container->replace(EventDispatcherInterface::class, $eventDispatcher);

            $adapter = $this->container->make(TestablePhpNonCliStart::class);
            $adapter->start();

            $ready = array_values(array_filter($events, static fn (object $e): bool => $e instanceof ServerReady));
            $this->assertCount(1, $ready);
            $this->assertSame('127.0.0.1', $ready[0]->host);
            $this->assertSame(8080, $ready[0]->port);
        } finally {
            if ($originalUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $originalUri;
            }
        }
    }

    public function testConstructorWithListenAllInterfacesSetsServerAddrFromNetworkLocal(): void
    {
        $app = $this->createMock(App::class);
        $serverOptions = new ServerOptions();
        $serverOptions->host = '0.0.0.0';
        $serverOptions->port = 8080;

        $this->container->set(ServerOptions::class, $serverOptions);
        $this->container->set(AppInterface::class, $app);

        $this->container->make(Php::class);

        $this->assertNotSame('0.0.0.0', $_SERVER['SERVER_ADDR']);
        $this->assertNotSame('', $_SERVER['SERVER_ADDR']);
    }

    public function testStartCliServerSetsPhpCliServerWorkersWhenWorkerNumGreaterThanOne(): void
    {
        $prev = getenv('PHP_CLI_SERVER_WORKERS');
        putenv('PHP_CLI_SERVER_WORKERS');

        try {
            $app = $this->createMock(App::class);
            $serverOptions = new ServerOptions();
            $serverOptions->host = '127.0.0.1';
            $serverOptions->port = 8080;
            $this->container->set(ServerOptions::class, $serverOptions);
            $this->container->set(AppInterface::class, $app);

            $router = $this->createMock(RouterInterface::class);
            $router->method('getPrefix')->willReturn('/api');
            $this->container->replace(RouterInterface::class, $router);

            $adapter = $this->container->make(TestablePhpCliHarness::class, [
                'settings' => ['worker_num' => 2],
            ]);

            try {
                $adapter->start();
                $this->fail('expected harness to stop instead of exit');
            } catch (RuntimeException $e) {
                $this->assertSame('cli_harness_stop', $e->getMessage());
            }

            $this->assertSame('2', getenv('PHP_CLI_SERVER_WORKERS'));
            $this->assertNotEmpty($adapter->shellCommands);
            $this->assertStringContainsString(' -S ', $adapter->shellCommands[0]);
        } finally {
            if ($prev === false) {
                putenv('PHP_CLI_SERVER_WORKERS');
            } else {
                putenv('PHP_CLI_SERVER_WORKERS=' . $prev);
            }
        }
    }

    public function testStartCliServerDoesNotSetPhpCliServerWorkersWhenWorkerNumIsOne(): void
    {
        putenv('PHP_CLI_SERVER_WORKERS');

        try {
            $app = $this->createMock(App::class);
            $serverOptions = new ServerOptions();
            $serverOptions->host = '127.0.0.1';
            $serverOptions->port = 8080;
            $this->container->set(ServerOptions::class, $serverOptions);
            $this->container->set(AppInterface::class, $app);

            $router = $this->createMock(RouterInterface::class);
            $router->method('getPrefix')->willReturn('');
            $this->container->replace(RouterInterface::class, $router);

            $adapter = $this->container->make(TestablePhpCliHarness::class, [
                'settings' => ['worker_num' => 1],
            ]);

            try {
                $adapter->start();
                $this->fail('expected harness to stop instead of exit');
            } catch (RuntimeException $e) {
                $this->assertSame('cli_harness_stop', $e->getMessage());
            }

            $this->assertFalse(getenv('PHP_CLI_SERVER_WORKERS'));
        } finally {
            putenv('PHP_CLI_SERVER_WORKERS');
        }
    }
}

/**
 * Exposes protected methods for high-value branch testing.
 *
 * Note: overrides header/readfile side-effects to keep unit tests hermetic.
 */
class TestablePhpAdapter extends Php
{
    public array $capturedHeaders = [];
    public array $readFiles = [];

    public function callHandleStaticRequest(string $uri): bool
    {
        return $this->handleStaticRequest($uri);
    }

    public function callPrepareGlobals(): void
    {
        $this->prepareGlobals();
    }

    protected function sendHttpHeader(string $line): void
    {
        $this->capturedHeaders[] = $line;
    }

    protected function outputFile(string $file): void
    {
        $this->readFiles[] = $file;
    }
}

/**
 * Forces non-CLI request path for {@see Php::start()} without touching {@see Php::startCliServer()}.
 */
final class TestablePhpNonCliStart extends Php
{
    protected function isCliRuntime(): bool
    {
        return false;
    }

    protected function handleStaticRequest(string $uri): bool
    {
        return false;
    }
}

/**
 * CLI {@see Php::start()} harness: records {@see shell_exec} command and avoids {@see exit}.
 */
final class TestablePhpCliHarness extends Php
{
    /** @var list<string> */
    public array $shellCommands = [];

    protected function logCliStart(string $command): void
    {
    }

    protected function runCliCommand(string $command): void
    {
        $this->shellCommands[] = $command;
    }

    protected function terminateProcess(): never
    {
        throw new RuntimeException('cli_harness_stop');
    }
}
