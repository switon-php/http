<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Adapter;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\RequestReceived;
use Switon\Http\RequestHandlerInterface;
use Switon\Http\Server\Adapter\Fpm;
use Switon\Http\Server\Adapter\Native\SenderInterface;
use Switon\Http\Server\Event\ServerReady;
use Switon\Http\ServerOptions;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for FPM server adapter.
 *
 * Tests FPM-specific server functionality.
 */
#[AllowMockObjectsWithoutExpectations]
class FpmTest extends TestCase
{
    #[Autowired] protected Fpm $fpm;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected RequestHandlerInterface $requestHandler;
    #[Autowired] protected SenderInterface $sender;
    #[Autowired] protected ServerOptions $serverOptions;

    protected function beforeSetUpHttpContainer(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
        $this->sender = $this->createMock(SenderInterface::class);
        $this->serverOptions = new ServerOptions();
        $this->serverOptions->host = 'localhost';
        $this->serverOptions->port = 8080;

        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(RequestHandlerInterface::class);
        $this->container->remove(SenderInterface::class);
        $this->container->remove(ServerOptions::class);

        $this->container->replace(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->replace(RequestHandlerInterface::class, $this->requestHandler);
        $this->container->replace(SenderInterface::class, $this->sender);
        $this->container->replace(ServerOptions::class, $this->serverOptions);
    }

    /**
     * Test FPM extends AbstractServer.
     */
    public function testFpmExtendsAbstractServer(): void
    {
        // Act & Assert
        $this->assertInstanceOf(\Switon\Http\AbstractServer::class, $this->fpm, 'FPM should extend AbstractServer');
    }

    /**
     * Test FPM start method dispatches events and handles request.
     */
    public function testFpmStartMethodDispatchesEventsAndHandlesRequest(): void
    {
        // Arrange
        $dispatchedEvents = [];
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $this->requestHandler->expects($this->once())
            ->method('handle');

        // Act
        $this->fpm->start();

        // Assert
        $this->assertCount(2, $dispatchedEvents, 'Should dispatch exactly 2 events');
        $this->assertInstanceOf(RequestReceived::class, $dispatchedEvents[0], 'First event should be RequestReceived');
        $this->assertInstanceOf(ServerReady::class, $dispatchedEvents[1], 'Second event should be ServerReady');
    }

    /**
     * Test FPM sendHeaders delegates to sender.
     */
    public function testFpmSendHeadersDelegatesToSender(): void
    {
        // Arrange
        $this->sender->expects($this->once())
            ->method('sendHeaders');

        // Act
        $this->fpm->sendHeaders();
    }

    /**
     * Test FPM sendBody delegates to sender.
     */
    public function testFpmSendBodyDelegatesToSender(): void
    {
        // Arrange
        $this->sender->expects($this->once())
            ->method('sendBody');

        // Act
        $this->fpm->sendBody();
    }

    /**
     * Test FPM ServerReady event contains correct server info.
     */
    public function testFpmServerReadyEventContainsCorrectServerInfo(): void
    {
        // Arrange
        $dispatchedEvents = [];
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        // Act
        $this->fpm->start();

        // Assert
        $this->assertCount(2, $dispatchedEvents, 'Should dispatch exactly 2 events');
        $serverReadyEvent = $dispatchedEvents[1];
        $this->assertInstanceOf(ServerReady::class, $serverReadyEvent, 'Second event should be ServerReady');
        $this->assertSame('localhost', $serverReadyEvent->host, 'ServerReady event should have correct host');
        $this->assertSame(8080, $serverReadyEvent->port, 'ServerReady event should have correct port');
    }

    /**
     * Test FPM RequestReceived event is dispatched with globals.
     */
    public function testFpmRequestReceivedEventIsDispatchedWithGlobals(): void
    {
        // Arrange
        $dispatchedEvents = [];
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        // Act
        $this->fpm->start();

        // Assert
        $this->assertCount(2, $dispatchedEvents, 'Should dispatch exactly 2 events');
        $requestReceivedEvent = $dispatchedEvents[0];
        $this->assertInstanceOf(RequestReceived::class, $requestReceivedEvent, 'First event should be RequestReceived');
        $this->assertIsArray($requestReceivedEvent->GET, 'RequestReceived should have GET array');
        $this->assertIsArray($requestReceivedEvent->POST, 'RequestReceived should have POST array');
        $this->assertIsArray($requestReceivedEvent->SERVER, 'RequestReceived should have SERVER array');
        $this->assertIsString($requestReceivedEvent->RAW_BODY, 'RequestReceived should have RAW_BODY string');
        $this->assertIsArray($requestReceivedEvent->COOKIE, 'RequestReceived should have COOKIE array');
        $this->assertIsArray($requestReceivedEvent->FILES, 'RequestReceived should have FILES array');
    }
}
