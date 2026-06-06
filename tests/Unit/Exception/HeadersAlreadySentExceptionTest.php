<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Exception;

use Switon\Http\Exception\HeadersAlreadySentException;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for HeadersAlreadySentException.
 *
 * Tests headers already sent exception functionality.
 */
class HeadersAlreadySentExceptionTest extends TestCase
{
    /**
     * Test HeadersAlreadySentException inherits from base Exception.
     */
    public function testHeadersAlreadySentExceptionInheritsFromBaseException(): void
    {
        // Arrange & Act
        $exception = HeadersAlreadySentException::at('/path/to/file.php', 42);

        // Assert
        $this->assertInstanceOf(\Switon\Http\Exception::class, $exception, 'HeadersAlreadySentException should inherit from HTTP Exception');
    }

    /**
     * Test HeadersAlreadySentException at factory creates exception with correct message.
     */
    public function testHeadersAlreadySentExceptionAtFactoryCreatesExceptionWithCorrectMessage(): void
    {
        // Arrange & Act
        $exception = HeadersAlreadySentException::at('/var/www/html/index.php', 25);

        // Assert
        $expectedMessage = 'Headers have already been sent in "/var/www/html/index.php" at line 25 - cannot modify headers after output';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should have formatted message with file and line');
    }

    /**
     * Test HeadersAlreadySentException at stores context data.
     */
    public function testHeadersAlreadySentExceptionAtStoresContextData(): void
    {
        // Arrange & Act
        $exception = HeadersAlreadySentException::at('/app/public/index.php', 15);

        // Assert
        $context = $exception->getContext();
        $this->assertEmpty($context, 'Context should be empty after message template processing');

        // The file and line are used in message template, so they're removed from context
        $expectedMessage = 'Headers have already been sent in "/app/public/index.php" at line 15 - cannot modify headers after output';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Message should be formatted with file and line');
    }

    /**
     * Test HeadersAlreadySentException at handles relative file paths.
     */
    public function testHeadersAlreadySentExceptionAtHandlesRelativeFilePaths(): void
    {
        // Arrange & Act
        $exception = HeadersAlreadySentException::at('src/Controller/HomeController.php', 100);

        // Assert
        $expectedMessage = 'Headers have already been sent in "src/Controller/HomeController.php" at line 100 - cannot modify headers after output';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle relative file paths');
    }

    /**
     * Test HeadersAlreadySentException at handles zero line number.
     */
    public function testHeadersAlreadySentExceptionAtHandlesZeroLineNumber(): void
    {
        // Arrange & Act
        $exception = HeadersAlreadySentException::at('/path/to/file.php', 0);

        // Assert
        $expectedMessage = 'Headers have already been sent in "/path/to/file.php" at line 0 - cannot modify headers after output';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle zero line number');
    }

    /**
     * Test HeadersAlreadySentException at handles large line numbers.
     */
    public function testHeadersAlreadySentExceptionAtHandlesLargeLineNumbers(): void
    {
        // Arrange & Act
        $exception = HeadersAlreadySentException::at('/huge/file.php', 99999);

        // Assert
        $expectedMessage = 'Headers have already been sent in "/huge/file.php" at line 99999 - cannot modify headers after output';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle large line numbers');
    }

    /**
     * Test HeadersAlreadySentException at handles empty file path.
     */
    public function testHeadersAlreadySentExceptionAtHandlesEmptyFilePath(): void
    {
        // Arrange & Act
        $exception = HeadersAlreadySentException::at('', 10);

        // Assert
        $expectedMessage = 'Headers have already been sent in "" at line 10 - cannot modify headers after output';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle empty file path');
    }

    /**
     * Test HeadersAlreadySentException getJson returns correct format.
     */
    public function testHeadersAlreadySentExceptionGetJsonReturnsCorrectFormat(): void
    {
        // Arrange & Act
        $exception = HeadersAlreadySentException::at('/app/index.php', 50);
        $json = $exception->getJson();

        // Assert
        $this->assertIsArray($json, 'getJson should return array');
        $this->assertArrayHasKey('code', $json, 'JSON should have code field');
        $this->assertArrayHasKey('msg', $json, 'JSON should have msg field');
        $this->assertSame(500, $json['code'], 'JSON code should be 500 (default for HTTP Exception)');
        $expectedMessage = 'Headers have already been sent in "/app/index.php" at line 50 - cannot modify headers after output';
        $this->assertSame($expectedMessage, $json['msg'], 'JSON msg should contain exception details');
    }

    /**
     * Test HeadersAlreadySentException getStatusCode returns 500.
     */
    public function testHeadersAlreadySentExceptionGetStatusCodeReturns500(): void
    {
        // Arrange & Act
        $exception = HeadersAlreadySentException::at('/test.php', 1);

        // Assert
        $this->assertSame(500, $exception->getStatusCode(), 'HeadersAlreadySentException should return 500 status code');
    }
}
