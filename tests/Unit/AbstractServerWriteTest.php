<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\App;
use Switon\Core\Attribute\Autowired;
use Switon\Http\AbstractServer;
use Switon\Http\Event\ChunkWriting;
use Switon\Http\Event\ChunkWritten;
use Switon\Http\Server\Adapter\Fpm;
use Switon\Http\Tests\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AbstractServerWriteTest extends TestCase
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    protected AbstractServer $server;

    protected function beforeSetUpHttpContainer(): void
    {
        // Replace default MockEventDispatcher with mock for event verification
        // This is called before ContextManager is created, so EventDispatcher hasn't been resolved yet
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->container->remove(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $this->container->replace(\Psr\EventDispatcher\EventDispatcherInterface::class, $this->eventDispatcher);
        // Also remove Switon-specific interface mapping if it exists
        $this->container->remove(\Switon\Eventing\EventDispatcherInterface::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->container->remove(App::class);
        $this->container->replace(App::class, ['id' => 'test-app']);

        $this->container->replace(\Switon\Http\Server\Adapter\Native\SenderInterface::class, $this->createStub(\Switon\Http\Server\Adapter\Native\SenderInterface::class));
        $this->container->replace(\Switon\Http\ResponseInterface::class, $this->createStub(\Switon\Http\ResponseInterface::class));
        $this->container->replace(\Switon\Routing\RouterInterface::class, $this->createStub(\Switon\Routing\RouterInterface::class));
        $this->container->replace(\Switon\Http\RequestHandlerInterface::class, $this->createStub(\Switon\Http\RequestHandlerInterface::class));

        // Property autowiring is automatically performed by parent::setUp()
        $this->server = $this->container->make(Fpm::class);
    }

    public function testWriteDispatchesChunkWritingEvent(): void
    {
        $chunk = 'test chunk';

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->logicalOr(
                $this->callback(function ($event) use ($chunk) {
                    return $event instanceof ChunkWriting && $event->chunk === $chunk;
                }),
                $this->isInstanceOf(ChunkWritten::class)
            ));

        $result = $this->server->write($chunk);

        $this->assertTrue($result);
    }

    public function testWriteUsesModifiedChunkFromEvent(): void
    {
        $originalChunk = 'original';
        $modifiedChunk = 'modified';
        $capturedChunk = null;

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($modifiedChunk, &$capturedChunk) {
                if ($event instanceof ChunkWriting) {
                    $event->chunk = $modifiedChunk;
                } elseif ($event instanceof ChunkWritten) {
                    $capturedChunk = $event->chunk;
                }
            });

        $this->server->write($originalChunk);

        $this->assertSame($modifiedChunk, $capturedChunk);
    }

    public function testWriteDispatchesChunkWrittenEvent(): void
    {
        $chunk = 'test chunk';

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->logicalOr(
                $this->isInstanceOf(ChunkWriting::class),
                $this->callback(function ($event) use ($chunk) {
                    return $event instanceof ChunkWritten
                        && $event->chunk === $chunk;
                })
            ));

        $this->server->write($chunk);
    }

    public function testWriteHandlesEmptyChunkCorrectly(): void
    {
        $chunk = '';

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->logicalOr(
                $this->callback(function ($event) {
                    return $event instanceof ChunkWriting && $event->chunk === '';
                }),
                $this->callback(function ($event) {
                    return $event instanceof ChunkWritten && $event->chunk === '';
                })
            ));

        $result = $this->server->write($chunk);

        $this->assertTrue($result);
    }

    public function testWriteDispatchesEventsWithCorrectChunkForNonEmptyChunk(): void
    {
        $chunk = 'hello';

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->logicalOr(
                $this->callback(function ($event) use ($chunk) {
                    return $event instanceof ChunkWriting && $event->chunk === $chunk;
                }),
                $this->callback(function ($event) use ($chunk) {
                    return $event instanceof ChunkWritten && $event->chunk === $chunk;
                })
            ));

        $result = $this->server->write($chunk);

        $this->assertTrue($result);
    }
}
