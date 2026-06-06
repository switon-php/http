<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Exception;

use Switon\Http\Exception\NotFoundException;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for NotFoundException.
 *
 * Tests 404 Not Found exception functionality.
 */
class NotFoundExceptionTest extends TestCase
{
    /**
     * Test NotFoundException returns correct status code.
     */
    public function testNotFoundExceptionReturnsCorrectStatusCode(): void
    {
        // Arrange & Act
        $exception = NotFoundException::of('Not found');

        // Assert
        $this->assertSame(404, $exception->getStatusCode(), 'NotFoundException should return 404 status code');
    }

    /**
     * Test NotFoundException can be created with message.
     */
    public function testNotFoundExceptionCanBeCreatedWithMessage(): void
    {
        // Arrange & Act
        $exception = NotFoundException::of('Resource not found');

        // Assert
        $this->assertSame('Resource not found', $exception->getMessage(), 'NotFoundException should store message');
    }

    /**
     * Test NotFoundException can be created with context.
     */
    public function testNotFoundExceptionCanBeCreatedWithContext(): void
    {
        // Arrange & Act
        $exception = NotFoundException::of('User {id} not found', ['id' => 123, 'table' => 'users']);

        // Assert
        $this->assertSame('User 123 not found', $exception->getMessage(), 'NotFoundException should process message template');
        $this->assertSame(['table' => 'users'], $exception->getContext(), 'NotFoundException should store remaining context');
    }

    /**
     * Test NotFoundException inherits from base Exception.
     */
    public function testNotFoundExceptionInheritsFromBaseException(): void
    {
        // Arrange & Act
        $exception = NotFoundException::of('Not found');

        // Assert
        $this->assertInstanceOf(\Switon\Core\Exception::class, $exception, 'NotFoundException should inherit from base Exception');
    }

    /**
     * Test NotFoundException getJson returns correct format.
     */
    public function testNotFoundExceptionGetJsonReturnsCorrectFormat(): void
    {
        // Arrange & Act
        $exception = NotFoundException::of('Not found');
        $json = $exception->getJson();

        // Assert
        $this->assertIsArray($json, 'getJson should return array');
        $this->assertArrayHasKey('code', $json, 'JSON should have code field');
        $this->assertArrayHasKey('msg', $json, 'JSON should have msg field');
        $this->assertSame(404, $json['code'], 'JSON code should be 404');
        $this->assertSame('Not found', $json['msg'], 'JSON msg should match exception message');
    }

    /**
     * Test NotFoundException can be raised.
     */
    public function testNotFoundExceptionCanBeRaised(): void
    {
        // Arrange & Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Page not found');

        // Act
        NotFoundException::raise('Page not found');
    }
}
