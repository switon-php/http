<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Http\ResponseContext;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for ResponseContext component.
 *
 * Tests response context state isolation for FPM/Swoole compatibility.
 */
class ResponseContextTest extends TestCase
{
    /**
     * Test ResponseContext has default status code 200.
     */
    public function testResponseContextHasDefaultStatusCode200(): void
    {
        // Arrange & Act
        $context = new ResponseContext();

        // Assert
        $this->assertSame(200, $context->status_code, 'status_code should be 200 by default');
    }

    /**
     * Test ResponseContext has default status text OK.
     */
    public function testResponseContextHasDefaultStatusTextOk(): void
    {
        // Arrange & Act
        $context = new ResponseContext();

        // Assert
        $this->assertSame('OK', $context->status_text, 'status_text should be OK by default');
    }

    /**
     * Test ResponseContext has default empty headers.
     */
    public function testResponseContextHasDefaultEmptyHeaders(): void
    {
        // Arrange & Act
        $context = new ResponseContext();

        // Assert
        $this->assertSame([], $context->headers, 'headers should be empty array by default');
    }

    /**
     * Test ResponseContext has default empty cookies.
     */
    public function testResponseContextHasDefaultEmptyCookies(): void
    {
        // Arrange & Act
        $context = new ResponseContext();

        // Assert
        $this->assertSame([], $context->cookies, 'cookies should be empty array by default');
    }

    /**
     * Test ResponseContext has default null content.
     */
    public function testResponseContextHasDefaultNullContent(): void
    {
        // Arrange & Act
        $context = new ResponseContext();

        // Assert
        $this->assertNull($context->content, 'content should be null by default');
    }

    /**
     * Test ResponseContext has default null file.
     */
    public function testResponseContextHasDefaultNullFile(): void
    {
        // Arrange & Act
        $context = new ResponseContext();

        // Assert
        $this->assertNull($context->file, 'file should be null by default');
    }

    /**
     * Test ResponseContext has default chunked false.
     */
    public function testResponseContextHasDefaultChunkedFalse(): void
    {
        // Arrange & Act
        $context = new ResponseContext();

        // Assert
        $this->assertFalse($context->chunked, 'chunked should be false by default');
    }

    /**
     * Test ResponseContext allows setting status code.
     */
    public function testResponseContextAllowsSettingStatusCode(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->status_code = 404;

        // Assert
        $this->assertSame(404, $context->status_code, 'status_code should be settable');
    }

    /**
     * Test ResponseContext allows setting status text.
     */
    public function testResponseContextAllowsSettingStatusText(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->status_text = 'Not Found';

        // Assert
        $this->assertSame('Not Found', $context->status_text, 'status_text should be settable');
    }

    /**
     * Test ResponseContext allows setting headers.
     */
    public function testResponseContextAllowsSettingHeaders(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->headers = ['Content-Type' => 'application/json'];

        // Assert
        $this->assertSame(['Content-Type' => 'application/json'], $context->headers, 'headers should be settable');
    }

    /**
     * Test ResponseContext allows setting cookies.
     */
    public function testResponseContextAllowsSettingCookies(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->cookies = ['session' => ['value' => 'abc123']];

        // Assert
        $this->assertSame(['session' => ['value' => 'abc123']], $context->cookies, 'cookies should be settable');
    }

    /**
     * Test ResponseContext allows setting content.
     */
    public function testResponseContextAllowsSettingContent(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->content = 'Hello World';

        // Assert
        $this->assertSame('Hello World', $context->content, 'content should be settable');
    }

    /**
     * Test ResponseContext allows setting file.
     */
    public function testResponseContextAllowsSettingFile(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->file = '/path/to/file.pdf';

        // Assert
        $this->assertSame('/path/to/file.pdf', $context->file, 'file should be settable');
    }

    /**
     * Test ResponseContext allows setting chunked.
     */
    public function testResponseContextAllowsSettingChunked(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->chunked = true;

        // Assert
        $this->assertTrue($context->chunked, 'chunked should be settable');
    }

    /**
     * Test ResponseContext allows setting mixed content types.
     */
    public function testResponseContextAllowsSettingMixedContentTypes(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->content = ['key' => 'value'];

        // Assert
        $this->assertSame(['key' => 'value'], $context->content, 'content should accept mixed types');
    }

    /**
     * Test ResponseContext isolates state between instances.
     */
    public function testResponseContextIsolatesStateBetweenInstances(): void
    {
        // Arrange
        $context1 = new ResponseContext();
        $context2 = new ResponseContext();

        // Act
        $context1->status_code = 404;
        $context1->content = 'Not Found';
        $context2->status_code = 500;
        $context2->content = 'Server Error';

        // Assert
        $this->assertSame(404, $context1->status_code, 'context1 should have its own status_code');
        $this->assertSame('Not Found', $context1->content, 'context1 should have its own content');
        $this->assertSame(500, $context2->status_code, 'context2 should have its own status_code');
        $this->assertSame('Server Error', $context2->content, 'context2 should have its own content');
    }

    /**
     * Test ResponseContext allows multiple headers.
     */
    public function testResponseContextAllowsMultipleHeaders(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->headers = [
            'Content-Type' => 'application/json',
            'X-Custom-Header' => 'custom-value',
            'Cache-Control' => 'no-cache',
        ];

        // Assert
        $this->assertCount(3, $context->headers, 'headers should support multiple entries');
        $this->assertSame('application/json', $context->headers['Content-Type']);
        $this->assertSame('custom-value', $context->headers['X-Custom-Header']);
        $this->assertSame('no-cache', $context->headers['Cache-Control']);
    }

    /**
     * Test ResponseContext allows multiple cookies.
     */
    public function testResponseContextAllowsMultipleCookies(): void
    {
        // Arrange
        $context = new ResponseContext();

        // Act
        $context->cookies = [
            'session' => ['value' => 'abc123', 'expires' => 3600],
            'user' => ['value' => 'john', 'expires' => 7200],
        ];

        // Assert
        $this->assertCount(2, $context->cookies, 'cookies should support multiple entries');
        $this->assertSame('abc123', $context->cookies['session']['value']);
        $this->assertSame('john', $context->cookies['user']['value']);
    }
}
