<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Exception;

use Switon\Http\Exception\TooManyRequestsException;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for TooManyRequestsException.
 *
 * Tests 429 Too Many Requests exception functionality.
 */
class TooManyRequestsExceptionTest extends TestCase
{
    /**
     * Test TooManyRequestsException returns correct status code.
     */
    public function testTooManyRequestsExceptionReturnsCorrectStatusCode(): void
    {
        // Arrange & Act
        $exception = TooManyRequestsException::of('Too many requests');

        // Assert
        $this->assertSame(429, $exception->getStatusCode(), 'TooManyRequestsException should return 429 status code');
    }

    /**
     * Test TooManyRequestsException can be created with message.
     */
    public function testTooManyRequestsExceptionCanBeCreatedWithMessage(): void
    {
        // Arrange & Act
        $exception = TooManyRequestsException::of('Rate limit exceeded');

        // Assert
        $this->assertSame('Rate limit exceeded', $exception->getMessage(), 'TooManyRequestsException should store message');
    }

    /**
     * Test TooManyRequestsException getJson returns custom format.
     */
    public function testTooManyRequestsExceptionGetJsonReturnsCustomFormat(): void
    {
        // Arrange & Act
        $exception = TooManyRequestsException::of('Rate limit exceeded');
        $json = $exception->getJson();

        // Assert
        $this->assertIsArray($json, 'getJson should return array');
        $this->assertArrayHasKey('code', $json, 'JSON should have code field');
        $this->assertArrayHasKey('msg', $json, 'JSON should have msg field');
        $this->assertSame(429, $json['code'], 'JSON code should be 429');
        $this->assertSame('Too Many Requests', $json['msg'], 'JSON msg should be fixed message');
    }

    /**
     * Test TooManyRequestsException raise method.
     */
    public function testTooManyRequestsExceptionRaiseMethod(): void
    {
        // Arrange & Assert
        $this->expectException(TooManyRequestsException::class);
        $this->expectExceptionMessage('Too many requests.');

        // Act
        TooManyRequestsException::raise('Too many requests.');
    }

    /**
     * Test TooManyRequestsException raise with context.
     */
    public function testTooManyRequestsExceptionRaiseWithContext(): void
    {
        // Arrange & Assert
        $this->expectException(TooManyRequestsException::class);
        $this->expectExceptionMessage('Too many requests.');

        try {
            // Act
            TooManyRequestsException::raise('Too many requests.', ['ip' => '127.0.0.1', 'limit' => 100]);
        } catch (TooManyRequestsException $e) {
            // Additional assertions
            $this->assertSame(['ip' => '127.0.0.1', 'limit' => 100], $e->getContext(), 'Exception should store context');
            throw $e;
        }
    }

    /**
     * Test TooManyRequestsException inherits from base Exception.
     */
    public function testTooManyRequestsExceptionInheritsFromBaseException(): void
    {
        // Arrange & Act
        $exception = TooManyRequestsException::of('Too many requests');

        // Assert
        $this->assertInstanceOf(\Switon\Core\Exception::class, $exception, 'TooManyRequestsException should inherit from base Exception');
    }

    /**
     * Test TooManyRequestsException can be created with context.
     */
    public function testTooManyRequestsExceptionCanBeCreatedWithContext(): void
    {
        // Arrange & Act
        $exception = TooManyRequestsException::of('Rate limit {limit} exceeded for IP {ip}', [
            'limit' => 100,
            'ip' => '192.168.1.1',
            'window' => '1 hour'
        ]);

        // Assert
        $this->assertSame('Rate limit 100 exceeded for IP 192.168.1.1', $exception->getMessage(), 'Exception should process message template');
        $this->assertSame(['window' => '1 hour'], $exception->getContext(), 'Exception should store remaining context');
    }
}
