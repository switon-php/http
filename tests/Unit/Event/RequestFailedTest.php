<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;
use Switon\Core\Exception;
use Switon\Eventing\EventLogInterface;
use Switon\Http\Event\RequestFailed;
use Switon\Http\Exception\NotFoundException;
use Switon\Http\Tests\TestCase;
use JsonSerializable;
use RuntimeException;
use stdClass;

/**
 * Test cases for RequestFailed event.
 *
 * Tests request failed event functionality.
 */
#[AllowMockObjectsWithoutExpectations]
class RequestFailedTest extends TestCase
{
    /**
     * Test RequestFailed can be instantiated with exception.
     */
    public function testRequestFailedCanBeInstantiatedWithException(): void
    {
        // Arrange
        $exception = new \Exception('Test error');

        // Act
        $event = new RequestFailed($exception);

        // Assert
        $this->assertSame($exception, $event->exception, 'RequestFailed should store exception');
    }

    /**
     * Test RequestFailed implements JsonSerializable.
     */
    public function testRequestFailedImplementsJsonSerializable(): void
    {
        // Arrange
        $exception = new \Exception('Test error');
        $event = new RequestFailed($exception);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event, 'RequestFailed should implement JsonSerializable');
    }

    /**
     * Test RequestFailed implements EventLogInterface.
     */
    public function testRequestFailedImplementsEventLogInterface(): void
    {
        // Arrange
        $exception = new \Exception('Test error');
        $event = new RequestFailed($exception);

        // Act & Assert
        $this->assertInstanceOf(EventLogInterface::class, $event, 'RequestFailed should implement EventLogInterface');
    }

    /**
     * Test RequestFailed jsonSerialize returns correct data.
     */
    public function testRequestFailedJsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $exception = new RuntimeException('Database connection failed', 1001);
        $event = new RequestFailed($exception);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertIsArray($data, 'jsonSerialize should return array');
        $this->assertArrayHasKey('exception', $data, 'Data should contain exception class');
        $this->assertArrayHasKey('message', $data, 'Data should contain message');
        $this->assertArrayHasKey('code', $data, 'Data should contain code');
        $this->assertArrayHasKey('file', $data, 'Data should contain file');
        $this->assertArrayHasKey('line', $data, 'Data should contain line');
        $this->assertArrayHasKey('trace', $data, 'Data should contain trace');

        $this->assertSame(RuntimeException::class, $data['exception'], 'Exception class should match');
        $this->assertSame('Database connection failed', $data['message'], 'Message should match');
        $this->assertSame(1001, $data['code'], 'Code should match');
        $this->assertIsString($data['file'], 'File should be string');
        $this->assertIsInt($data['line'], 'Line should be integer');
        $this->assertIsString($data['trace'], 'Trace should be string');
    }

    /**
     * Test RequestFailed log method with server error (5xx).
     */
    public function testRequestFailedLogMethodWithServerError(): void
    {
        // Arrange
        $exception = Exception::of('Internal server error');
        $event = new RequestFailed($exception);
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->once())
            ->method('log')
            ->with(
                'error', // Should log as ERROR for 5xx status codes
                $this->isInstanceOf(\Switon\Core\Categorized::class),
                $this->arrayHasKey('exception')
            );

        // Act
        $event->log($event, $logger);
    }

    /**
     * Test RequestFailed log method with client error (4xx).
     */
    public function testRequestFailedLogMethodWithClientError(): void
    {
        // Arrange
        $exception = NotFoundException::of('Page not found');
        $event = new RequestFailed($exception);
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->once())
            ->method('log')
            ->with(
                'debug', // Should log as DEBUG for 4xx status codes
                $this->isInstanceOf(\Switon\Core\Categorized::class),
                $this->arrayHasKey('exception')
            );

        // Act
        $event->log($event, $logger);
    }

    /**
     * Test RequestFailed log method with non-HTTP exception.
     */
    public function testRequestFailedLogMethodWithNonHttpException(): void
    {
        // Arrange
        $exception = new RuntimeException('Generic error');
        $event = new RequestFailed($exception);
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->once())
            ->method('log')
            ->with(
                'error', // Should log as ERROR for non-HTTP exceptions (defaults to 500)
                $this->isInstanceOf(\Switon\Core\Categorized::class),
                $this->arrayHasKey('exception')
            );

        // Act
        $event->log($event, $logger);
    }

    /**
     * Test RequestFailed log method includes exception context.
     */
    public function testRequestFailedLogMethodIncludesExceptionContext(): void
    {
        // Arrange
        $exception = Exception::of('User {id} not found', ['id' => 123, 'table' => 'users']);
        $event = new RequestFailed($exception);
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->once())
            ->method('log')
            ->with(
                $this->anything(),
                $this->isInstanceOf(\Switon\Core\Categorized::class),
                $this->callback(function ($context) {
                    return isset($context['exception']) &&
                        isset($context['message']) &&
                        isset($context['table']) && // From exception context
                        $context['table'] === 'users';
                })
            );

        // Act
        $event->log($event, $logger);
    }

    /**
     * Test RequestFailed log method ignores wrong event type.
     */
    public function testRequestFailedLogMethodIgnoresWrongEventType(): void
    {
        // Arrange
        $exception = new \Exception('Test error');
        $event = new RequestFailed($exception);
        $wrongEvent = new stdClass();
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->never())
            ->method('log');

        // Act
        $event->log($wrongEvent, $logger);
    }

    /**
     * Test RequestFailed with Switon Exception includes context.
     */
    public function testRequestFailedWithSwitonExceptionIncludesContext(): void
    {
        // Arrange
        $exception = Exception::of('Operation failed', ['operation' => 'delete', 'resource' => 'user']);
        $event = new RequestFailed($exception);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertSame(Exception::class, $data['exception'], 'Exception class should be Switon Exception');
        $this->assertSame('Operation failed', $data['message'], 'Message should match');
    }
}
