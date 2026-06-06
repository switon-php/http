<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Exception;

use Switon\Http\Exception\InvalidControllerException;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for InvalidControllerException.
 *
 * Tests invalid controller exception functionality.
 */
class InvalidControllerExceptionTest extends TestCase
{
    /**
     * Test InvalidControllerException inherits from HTTP Exception.
     */
    public function testInvalidControllerExceptionInheritsFromHttpException(): void
    {
        // Arrange & Act
        $exception = InvalidControllerException::of('Invalid controller.');

        // Assert
        $this->assertInstanceOf(\Switon\Http\Exception::class, $exception, 'InvalidControllerException should inherit from HTTP Exception');
    }

    /**
     * Test InvalidControllerException of factory creates exception with correct message.
     */
    public function testInvalidControllerExceptionOfFactoryCreatesExceptionWithCorrectMessage(): void
    {
        // Arrange & Act
        $exception = InvalidControllerException::of(
            'Invalid controller path "{path}".',
            ['path' => 'App\\Controller\\NonExistentController']
        );

        // Assert
        $expectedMessage = 'Invalid controller path "App\\Controller\\NonExistentController".';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should have formatted message with controller path');
    }

    /**
     * Test InvalidControllerException of stores context data.
     */
    public function testInvalidControllerExceptionOfStoresContextData(): void
    {
        // Arrange & Act
        $exception = InvalidControllerException::of(
            'Invalid controller path "{path}".',
            ['path' => 'App\\Controller\\BadController', 'extra' => 'data']
        );

        // Assert
        $context = $exception->getContext();
        $this->assertArrayHasKey('extra', $context, 'Context should contain extra data');
        $this->assertSame('data', $context['extra'], 'Context extra should be preserved');
    }

    /**
     * Test InvalidControllerException of for missing request mapping.
     */
    public function testInvalidControllerExceptionOfForMissingRequestMapping(): void
    {
        // Arrange & Act
        $exception = InvalidControllerException::of(
            'Controller "{controller}" does not have #[RequestMapping] attribute.',
            ['controller' => 'HomeController']
        );

        // Assert
        $expectedMessage = 'Controller "HomeController" does not have #[RequestMapping] attribute.';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should have formatted message with controller name');
    }

    /**
     * Test InvalidControllerException of handles empty controller path.
     */
    public function testInvalidControllerExceptionOfHandlesEmptyControllerPath(): void
    {
        // Arrange & Act
        $exception = InvalidControllerException::of(
            'Invalid controller path "{path}".',
            ['path' => '']
        );

        // Assert
        $expectedMessage = 'Invalid controller path "".';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle empty controller path');
    }

    /**
     * Test InvalidControllerException of handles namespaced controller paths.
     */
    public function testInvalidControllerExceptionOfHandlesNamespacedControllerPaths(): void
    {
        // Arrange & Act
        $exception = InvalidControllerException::of(
            'Invalid controller path "{path}".',
            ['path' => 'App\\Areas\\Admin\\Controller\\DashboardController']
        );

        // Assert
        $expectedMessage = 'Invalid controller path "App\\Areas\\Admin\\Controller\\DashboardController".';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle namespaced controller paths');
    }

    /**
     * Test InvalidControllerException getJson returns correct format.
     */
    public function testInvalidControllerExceptionGetJsonReturnsCorrectFormat(): void
    {
        // Arrange & Act
        $exception = InvalidControllerException::of('Invalid controller.');
        $json = $exception->getJson();

        // Assert
        $this->assertIsArray($json, 'getJson should return array');
        $this->assertArrayHasKey('code', $json, 'JSON should have code field');
        $this->assertArrayHasKey('msg', $json, 'JSON should have msg field');
        $this->assertSame(500, $json['code'], 'JSON code should be 500 (default for HTTP Exception)');
        $this->assertSame('Invalid controller.', $json['msg'], 'JSON msg should contain exception details');
    }

    /**
     * Test InvalidControllerException getStatusCode returns 500.
     */
    public function testInvalidControllerExceptionGetStatusCodeReturns500(): void
    {
        // Arrange & Act
        $exception = InvalidControllerException::of('Invalid controller.');

        // Assert
        $this->assertSame(500, $exception->getStatusCode(), 'InvalidControllerException should return 500 status code');
    }
}
