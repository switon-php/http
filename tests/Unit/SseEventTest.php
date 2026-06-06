<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Http\SseEvent;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for SseEvent component.
 *
 * Tests Server-Sent Events (SSE) formatting and protocol compliance.
 */
class SseEventTest extends TestCase
{
    /**
     * Test data() creates event with data field.
     */
    public function testDataCreatesEventWithDataField(): void
    {
        // Arrange & Act
        $event = SseEvent::data('test message');

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('data: test message', $output, 'Event should contain data field');
        $this->assertStringEndsWith("\r\n\r\n", $output, 'Event should end with double CRLF');
    }

    /**
     * Test data() encodes array as JSON.
     */
    public function testDataEncodesArrayAsJson(): void
    {
        // Arrange & Act
        $event = SseEvent::data(['key' => 'value', 'number' => 123]);

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('data: {"key":"value","number":123}', $output, 'Event should encode array as JSON');
    }

    /**
     * Test event() creates named event with data.
     */
    public function testEventCreatesNamedEventWithData(): void
    {
        // Arrange & Act
        $event = SseEvent::event('message', 'test data');

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('event: message', $output, 'Event should contain event field');
        $this->assertStringContainsString('data: test data', $output, 'Event should contain data field');
    }

    /**
     * Test event() encodes data as JSON.
     */
    public function testEventEncodesDataAsJson(): void
    {
        // Arrange & Act
        $event = SseEvent::event('update', ['status' => 'success']);

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('event: update', $output, 'Event should contain event field');
        $this->assertStringContainsString('data: {"status":"success"}', $output, 'Event should encode data as JSON');
    }

    /**
     * Test withId() adds ID field.
     */
    public function testWithIdAddsIdField(): void
    {
        // Arrange
        $event = SseEvent::data('test message');

        // Act
        $eventWithId = $event->withId('123');

        // Assert
        $output = (string)$eventWithId;
        $this->assertStringContainsString('id: 123', $output, 'Event should contain id field');
        $this->assertStringContainsString('data: test message', $output, 'Event should still contain data field');
    }

    /**
     * Test withId() returns new instance.
     */
    public function testWithIdReturnsNewInstance(): void
    {
        // Arrange
        $event = SseEvent::data('test message');

        // Act
        $eventWithId = $event->withId('123');

        // Assert
        $this->assertNotSame($event, $eventWithId, 'withId() should return new instance');
        $this->assertStringNotContainsString('id:', (string)$event, 'Original event should not have id field');
        $this->assertStringContainsString('id: 123', (string)$eventWithId, 'New event should have id field');
    }

    /**
     * Test withRetry() adds retry field.
     */
    public function testWithRetryAddsRetryField(): void
    {
        // Arrange
        $event = SseEvent::data('test message');

        // Act
        $eventWithRetry = $event->withRetry(3000);

        // Assert
        $output = (string)$eventWithRetry;
        $this->assertStringContainsString('retry: 3000', $output, 'Event should contain retry field');
        $this->assertStringContainsString('data: test message', $output, 'Event should still contain data field');
    }

    /**
     * Test withRetry() returns new instance.
     */
    public function testWithRetryReturnsNewInstance(): void
    {
        // Arrange
        $event = SseEvent::data('test message');

        // Act
        $eventWithRetry = $event->withRetry(5000);

        // Assert
        $this->assertNotSame($event, $eventWithRetry, 'withRetry() should return new instance');
        $this->assertStringNotContainsString('retry:', (string)$event, 'Original event should not have retry field');
        $this->assertStringContainsString('retry: 5000', (string)$eventWithRetry, 'New event should have retry field');
    }

    /**
     * Test chaining withId() and withRetry().
     */
    public function testChainingWithIdAndWithRetry(): void
    {
        // Arrange & Act
        $event = SseEvent::data('test message')
            ->withId('456')
            ->withRetry(2000);

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('id: 456', $output, 'Event should contain id field');
        $this->assertStringContainsString('retry: 2000', $output, 'Event should contain retry field');
        $this->assertStringContainsString('data: test message', $output, 'Event should contain data field');
    }

    /**
     * Test constructor with custom data array.
     */
    public function testConstructorWithCustomDataArray(): void
    {
        // Arrange & Act
        $event = new SseEvent([
            'event' => 'custom',
            'data' => 'custom data',
            'id' => '789',
        ]);

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('event: custom', $output, 'Event should contain event field');
        $this->assertStringContainsString('data: custom data', $output, 'Event should contain data field');
        $this->assertStringContainsString('id: 789', $output, 'Event should contain id field');
    }

    /**
     * Test empty event.
     */
    public function testEmptyEvent(): void
    {
        // Arrange & Act
        $event = new SseEvent();

        // Assert
        $output = (string)$event;
        $this->assertSame("\r\n", $output, 'Empty event should only contain double CRLF');
    }

    /**
     * Test event format compliance.
     */
    public function testEventFormatCompliance(): void
    {
        // Arrange & Act
        $event = SseEvent::event('message', 'test')
            ->withId('1')
            ->withRetry(1000);

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('event: message', $output, 'Event should contain event field');
        $this->assertStringContainsString('data: test', $output, 'Event should contain data field');
        $this->assertStringContainsString('id: 1', $output, 'Event should contain id field');
        $this->assertStringContainsString('retry: 1000', $output, 'Event should contain retry field');
        $this->assertStringEndsWith("\r\n\r\n", $output, 'Event should end with double CRLF');
    }

    /**
     * Test data with special characters.
     */
    public function testDataWithSpecialCharacters(): void
    {
        // Arrange & Act
        $event = SseEvent::data('Line 1\nLine 2');

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('data: Line 1\nLine 2', $output, 'Event should preserve special characters');
    }

    /**
     * Test event with numeric data.
     */
    public function testEventWithNumericData(): void
    {
        // Arrange & Act
        $event = SseEvent::data(42);

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('data: 42', $output, 'Event should handle numeric data');
    }

    /**
     * Test event with boolean data.
     */
    public function testEventWithBooleanData(): void
    {
        // Arrange & Act
        $event = SseEvent::data(true);

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('data: true', $output, 'Event should encode boolean as JSON');
    }

    /**
     * Test event with null data.
     */
    public function testEventWithNullData(): void
    {
        // Arrange & Act
        $event = SseEvent::data(null);

        // Assert
        $output = (string)$event;
        $this->assertStringContainsString('data: null', $output, 'Event should encode null as JSON');
    }
}
