<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Adapter;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\App;
use Switon\Http\Request;
use Switon\Http\RequestHandler;
use Switon\Http\Response;
use Switon\Http\Server\Adapter\Swoole;
use Switon\Http\Server\Adapter\SwooleContext;
use Switon\Http\Server\Event\ServerStart;
use Switon\Http\Server\Event\ServerStarted;
use Switon\Http\Server\StaticHandlerInterface;
use Switon\Http\ServerOptions;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server as SwooleServer;

#[AllowMockObjectsWithoutExpectations]
#[RequiresPhpExtension('swoole')]
class SwooleTest extends TestCase
{
    protected Swoole $swooleAdapter;
    protected MockObject|EventDispatcherInterface $eventDispatcher;
    protected MockObject|StaticHandlerInterface $staticHandler;
    protected MockObject|RequestHandler $requestHandler;
    protected MockObject|Request $request;
    protected MockObject|Response $response;
    protected MockObject|RouterInterface $router;
    protected ServerOptions $serverOptions;
    protected SwooleContext $context;

    protected function setUp(): void
    {
        // Call parent setUp first to initialize container and contextManager
        parent::setUp();

        // Create mocks for dependencies
        $mockApp = $this->createMock(App::class);
        $mockApp->method('env')->willReturn('testing');

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->staticHandler = $this->createMock(StaticHandlerInterface::class);
        $this->requestHandler = $this->createMock(RequestHandler::class);
        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);
        $this->router = $this->createMock(RouterInterface::class);

        // Create ServerOptions with test values
        $this->serverOptions = new ServerOptions();
        $this->serverOptions->type = 'swoole';
        $this->serverOptions->host = '127.0.0.1';
        $this->serverOptions->port = 8080;
        $this->context = new SwooleContext();

        // Register dependencies in container
        $this->container->replace(App::class, fn () => $mockApp);
        $this->container->replace(EventDispatcherInterface::class, fn () => $this->eventDispatcher);
        $this->container->replace(StaticHandlerInterface::class, fn () => $this->staticHandler);
        $this->container->replace(RequestHandler::class, fn () => $this->requestHandler);
        $this->container->replace(Request::class, fn () => $this->request);
        $this->container->replace(Response::class, fn () => $this->response);
        $this->container->replace(RouterInterface::class, fn () => $this->router);
        $this->container->replace(ServerOptions::class, fn () => $this->serverOptions);

        // Create Swoole adapter using container (which will inject all dependencies)
        $this->swooleAdapter = $this->container->make(Swoole::class);
    }

    /**
     * Test Swoole adapter constructor initializes global server variables correctly.
     *
     * Verified through global $_SERVER which is set during construction.
     */
    public function testConstructorInitializesServerVariables(): void
    {
        // Assert - constructor sets DOCUMENT_ROOT in global $_SERVER
        $this->assertArrayHasKey('DOCUMENT_ROOT', $_SERVER);
    }

    /**
     * Test getContext returns SwooleContext from context manager.
     */
    public function testGetContextReturnsSwooleContext(): void
    {
        // Act
        $result = $this->swooleAdapter->getContext();

        // Assert
        $this->assertInstanceOf(SwooleContext::class, $result);
    }

    /**
     * Test onStart dispatches ServerStart and ServerStarted events.
     */
    public function testOnStartDispatchesEvents(): void
    {
        // Arrange
        $server = $this->createMock(SwooleServer::class);
        $server->setting = ['worker_num' => 4];
        $server->master_pid = 12345;

        // App mock is already configured in setUp() to return 'testing' for env()

        $call = 0;
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$call) {
                $call++;
                if ($call === 1) {
                    $this->assertInstanceOf(ServerStart::class, $event);
                } elseif ($call === 2) {
                    $this->assertInstanceOf(ServerStarted::class, $event);
                }
                return $event;
            });

        // Act
        $this->swooleAdapter->onStart($server);

        // Assert - test passes if expectations are met
        $this->assertTrue(true);
    }

    /**
     * Test onRequest handles favicon.ico requests.
     */
    public function testOnRequestHandlesFaviconRequest(): void
    {
        // Arrange
        $swooleRequest = $this->createMock(SwooleRequest::class);
        $swooleRequest->server = ['request_uri' => '/favicon.ico'];

        $swooleResponse = $this->createMock(SwooleResponse::class);
        $swooleResponse->expects($this->once())
            ->method('status')
            ->with(404);
        $swooleResponse->expects($this->once())
            ->method('end');

        // Act
        $this->swooleAdapter->onRequest($swooleRequest, $swooleResponse);

        // Assert - test passes if expectations are met
        $this->assertTrue(true);
    }

    /**
     * Test onRequest handles static file requests when static handler is enabled.
     *
     * Configure settings through container before creating adapter.
     */
    public function testOnRequestHandlesStaticFileRequests(): void
    {
        // Arrange - create adapter with static handler enabled via container config
        $this->container->set('swoole.settings', ['enable_static_handler' => true]);
        $swooleAdapterWithStatic = $this->container->make(Swoole::class, [
            'settings' => ['enable_static_handler' => true],
        ]);

        $swooleRequest = $this->createMock(SwooleRequest::class);
        $swooleRequest->server = ['request_uri' => '/static/style.css'];
        $swooleRequest->header = [];
        $swooleRequest->get = [];
        $swooleRequest->post = [];
        $swooleRequest->cookie = [];
        $swooleRequest->files = [];
        $swooleRequest->expects($this->once())
            ->method('rawContent')
            ->willReturn('');

        $swooleResponse = $this->createMock(SwooleResponse::class);

        $this->staticHandler->expects($this->once())
            ->method('isFile')
            ->with('/static/style.css')
            ->willReturn(true);

        $this->staticHandler->expects($this->once())
            ->method('getFile')
            ->with('/static/style.css')
            ->willReturn('/path/to/style.css');

        $this->staticHandler->expects($this->once())
            ->method('getMimeType')
            ->with('/path/to/style.css')
            ->willReturn('text/css');

        $swooleResponse->expects($this->once())
            ->method('header')
            ->with('Content-Type', 'text/css');

        $swooleResponse->expects($this->once())
            ->method('sendfile')
            ->with('/path/to/style.css');

        // prepareGlobals dispatches RequestReceived, plus AssetSending and AssetSent
        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch');

        // Act
        $swooleAdapterWithStatic->onRequest($swooleRequest, $swooleResponse);

        // Assert - test passes if expectations are met
        $this->assertTrue(true);
    }


    /**
     * Test sendHeaders dispatches appropriate events.
     */
    public function testSendHeadersDispatchesEvents(): void
    {
        // Arrange
        $swooleResponse = $this->createMock(SwooleResponse::class);

        // Set up context with the mocked swooleResponse
        $context = $this->swooleAdapter->getContext();
        $context->response = $swooleResponse;

        // Act & Assert - just verify the method can be called without errors
        // Detailed behavior is tested in integration tests
        $this->swooleAdapter->sendHeaders();

        $this->assertTrue(true);
    }

    /**
     * Test sendBody can be called without errors.
     */
    public function testSendBodyCanBeCalled(): void
    {
        // Arrange
        $swooleResponse = $this->createMock(SwooleResponse::class);
        $swooleResponse->method('end')->willReturn(true);

        // Set up context with the mocked swooleResponse
        $context = $this->swooleAdapter->getContext();
        $context->response = $swooleResponse;

        // Act & Assert - just verify the method can be called without errors
        // Detailed behavior is tested in integration tests
        $this->swooleAdapter->sendBody();

        $this->assertTrue(true);
    }


    /**
     * Test write method writes chunks correctly.
     */
    public function testWriteWritesChunksCorrectly(): void
    {
        // Arrange
        $swooleResponse = $this->createMock(SwooleResponse::class);

        // Set up context with the mocked swooleResponse
        $context = $this->swooleAdapter->getContext();
        $context->response = $swooleResponse;

        $swooleResponse->expects($this->once())
            ->method('write')
            ->with('chunk data')
            ->willReturn(true);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        // Act
        $result = $this->swooleAdapter->write('chunk data');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test write method handles empty chunks.
     */
    public function testWriteHandlesEmptyChunks(): void
    {
        // Arrange
        $swooleResponse = $this->createMock(SwooleResponse::class);

        // Set up context with the mocked swooleResponse
        $context = $this->swooleAdapter->getContext();
        $context->response = $swooleResponse;

        $swooleResponse->expects($this->once())
            ->method('end')
            ->willReturn(true);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        // Act
        $result = $this->swooleAdapter->write('');

        // Assert
        $this->assertTrue($result);
    }
}
