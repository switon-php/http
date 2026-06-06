<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Core\NotFoundInterface;
use Switon\Core\StopFlow;
use Switon\Http\Exception\ForbiddenException;
use Switon\Http\Exception\NotFoundException;
use Switon\Http\Exception\UnauthorizedException;
use Switon\Http\ExceptionDispatcher;
use Switon\Http\ExceptionHandlerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Principal\Exception\NotAuthenticatedException;
use Throwable;
use Error;
use Exception;
use LogicException;
use RuntimeException;

/**
 * Test cases for ExceptionDispatcher component.
 *
 * Tests exception handler resolution and dispatching logic.
 */
#[AllowMockObjectsWithoutExpectations]
class ExceptionDispatcherTest extends TestCase
{
    /**
     * Test dispatch() finds exact class match handler.
     */
    public function testDispatchFindsExactClassMatchHandler(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');
        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($exception);

        $dispatcher = new TestableExceptionDispatcher([
            UnauthorizedException::class => $handler,
        ]);

        // Act
        $dispatcher->dispatch($exception);

        // Assert - expectations verified by mock
    }

    /**
     * Test dispatch() finds parent class handler.
     */
    public function testDispatchFindsParentClassHandler(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');
        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($exception);

        $dispatcher = new TestableExceptionDispatcher([
            \Switon\Core\Exception::class => $handler,
        ]);

        // Act
        $dispatcher->dispatch($exception);

        // Assert - expectations verified by mock
    }

    /**
     * Test dispatch() finds interface handler.
     */
    public function testDispatchFindsInterfaceHandler(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');
        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($exception);

        $dispatcher = new TestableExceptionDispatcher([
            Throwable::class => $handler,
        ]);

        // Act
        $dispatcher->dispatch($exception);

        // Assert - expectations verified by mock
    }

    public function testDispatchFindsHandlerRegisteredForNotFoundInterface(): void
    {
        $exception = null;
        try {
            NotFoundException::raise('Missing');
        } catch (NotFoundException $e) {
            $exception = $e;
        }
        $this->assertInstanceOf(NotFoundException::class, $exception);

        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($exception);

        $dispatcher = new TestableExceptionDispatcher([
            NotFoundInterface::class => $handler,
        ]);

        $dispatcher->dispatch($exception);
    }

    /**
     * Test dispatch() prefers exact match over parent class.
     */
    public function testDispatchPrefersExactMatchOverParentClass(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');
        $exactHandler = $this->createMock(ExceptionHandlerInterface::class);
        $exactHandler->expects($this->once())
            ->method('handle')
            ->with($exception);

        $parentHandler = $this->createMock(ExceptionHandlerInterface::class);
        $parentHandler->expects($this->never())
            ->method('handle');

        $dispatcher = new TestableExceptionDispatcher([
            UnauthorizedException::class => $exactHandler,
            \Switon\Core\Exception::class => $parentHandler,
        ]);

        // Act
        $dispatcher->dispatch($exception);

        // Assert - expectations verified by mock
    }

    /**
     * Test dispatch() handles StopFlow exception from handler.
     */
    public function testDispatchHandlesStopFlowFromHandler(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');
        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($exception)
            ->willThrowException(StopFlow::abort());

        $dispatcher = new TestableExceptionDispatcher([
            UnauthorizedException::class => $handler,
        ]);

        // Act & Assert - should not throw
        $dispatcher->dispatch($exception);
        $this->assertTrue(true, 'dispatch() should suppress StopFlow from handler');
    }

    /**
     * Test dispatch() does nothing when no handler found.
     */
    public function testDispatchDoesNothingWhenNoHandlerFound(): void
    {
        // Arrange
        $exception = new LogicException('Logic error');
        $dispatcher = new TestableExceptionDispatcher([
            UnauthorizedException::class => $this->createMock(ExceptionHandlerInterface::class),
        ]);

        // Act & Assert - should not throw
        $dispatcher->dispatch($exception);
        $this->assertTrue(true, 'dispatch() should do nothing when no handler found');
    }

    /**
     * Test dispatch() handles NotAuthenticatedException.
     */
    public function testDispatchHandlesNotAuthenticatedException(): void
    {
        // Arrange
        $exception = NotAuthenticatedException::of('Not authenticated');
        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($exception);

        $dispatcher = new TestableExceptionDispatcher([
            NotAuthenticatedException::class => $handler,
        ]);

        // Act
        $dispatcher->dispatch($exception);

        // Assert - expectations verified by mock
    }

    /**
     * Test dispatch() handles ForbiddenException.
     */
    public function testDispatchHandlesForbiddenException(): void
    {
        // Arrange
        $exception = ForbiddenException::of('Forbidden');
        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($exception);

        $dispatcher = new TestableExceptionDispatcher([
            ForbiddenException::class => $handler,
        ]);

        // Act
        $dispatcher->dispatch($exception);

        // Assert - expectations verified by mock
    }

    public function testDispatchWithEmptyHandlersMapDoesNotInvokeAnyHandler(): void
    {
        $dispatcher = new TestableExceptionDispatcher([]);

        $dispatcher->dispatch(new RuntimeException('orphan'));

        $this->assertTrue(true);
    }

    public function testDispatchMatchesParentExceptionClassBeforeThrowableFallback(): void
    {
        $exception = new LogicException('logic');

        $parentHandler = $this->createMock(ExceptionHandlerInterface::class);
        $parentHandler->expects($this->once())
            ->method('handle')
            ->with($exception);

        $throwableHandler = $this->createMock(ExceptionHandlerInterface::class);
        $throwableHandler->expects($this->never())
            ->method('handle');

        $dispatcher = new TestableExceptionDispatcher([
            Exception::class => $parentHandler,
            Throwable::class => $throwableHandler,
        ]);

        $dispatcher->dispatch($exception);
    }

    public function testDispatchResolvesPhpErrorUsingThrowableHandler(): void
    {
        $error = new Error('fatal');
        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($error);

        $dispatcher = new TestableExceptionDispatcher([
            Throwable::class => $handler,
        ]);

        $dispatcher->dispatch($error);
    }

    /**
     * Test dispatch() uses fallback Throwable handler.
     */
    public function testDispatchUsesFallbackThrowableHandler(): void
    {
        // Arrange
        $exception = new Exception('Generic exception');
        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($exception);

        $dispatcher = new TestableExceptionDispatcher([
            Throwable::class => $handler,
        ]);

        // Act
        $dispatcher->dispatch($exception);

        // Assert - expectations verified by mock
    }
}

/**
 * Testable subclass of ExceptionDispatcher for unit testing.
 */
class TestableExceptionDispatcher extends ExceptionDispatcher
{
    public function __construct(array $handlers = [])
    {
        $this->handlers = $handlers;
    }
}
