<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Exception;

use Switon\Http\Exception\ControllerNotFoundException;
use Switon\Http\Exception\NotFoundException;
use Switon\Http\Tests\TestCase;
use ReflectionException;

/**
 * Test cases for ControllerNotFoundException.
 *
 * Tests controller not found exception functionality.
 */
class ControllerNotFoundExceptionTest extends TestCase
{
    /**
     * Test ControllerNotFoundException inherits from NotFoundException.
     */
    public function testControllerNotFoundExceptionInheritsFromNotFoundException(): void
    {
        // Arrange & Act
        $exception = ControllerNotFoundException::of('Controller not found.');

        // Assert
        $this->assertInstanceOf(NotFoundException::class, $exception, 'ControllerNotFoundException should inherit from NotFoundException');
        $this->assertSame(404, $exception->getStatusCode(), 'ControllerNotFoundException should return 404 status code');
    }

    /**
     * Test ControllerNotFoundException of factory creates exception with correct message.
     */
    public function testControllerNotFoundExceptionOfFactoryCreatesExceptionWithCorrectMessage(): void
    {
        // Arrange & Act
        $exception = ControllerNotFoundException::of(
            'Controller class "{controller}" does not exist or cannot be loaded.',
            ['controller' => 'App\\Controller\\UserController']
        );

        // Assert
        $expectedMessage = 'Controller class "App\\Controller\\UserController" does not exist or cannot be loaded.';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should have formatted message');
    }

    /**
     * Test ControllerNotFoundException of stores context data.
     */
    public function testControllerNotFoundExceptionOfStoresContextData(): void
    {
        // Arrange & Act
        $exception = ControllerNotFoundException::of(
            'Controller class "{controller}" does not exist.',
            ['controller' => 'PostController', 'extra' => 'data']
        );

        // Assert
        $context = $exception->getContext();
        $this->assertArrayHasKey('extra', $context, 'Context should contain extra data');
        $this->assertSame('data', $context['extra'], 'Context extra should be preserved');
    }

    /**
     * Test ControllerNotFoundException of with previous exception.
     */
    public function testControllerNotFoundExceptionOfWithPreviousException(): void
    {
        // Arrange
        $previousException = new ReflectionException('Class not found');

        // Act
        $exception = ControllerNotFoundException::of('Controller not found.', [], 0, $previousException);

        // Assert
        $this->assertSame($previousException, $exception->getPrevious(), 'Exception should store previous exception');
    }

    /**
     * Test ControllerNotFoundException of without previous exception.
     */
    public function testControllerNotFoundExceptionOfWithoutPreviousException(): void
    {
        // Arrange & Act
        $exception = ControllerNotFoundException::of('Controller not found.');

        // Assert
        $this->assertNull($exception->getPrevious(), 'Exception should not have previous exception when not provided');
    }

    /**
     * Test ControllerNotFoundException of handles empty controller name.
     */
    public function testControllerNotFoundExceptionOfHandlesEmptyControllerName(): void
    {
        // Arrange & Act
        $exception = ControllerNotFoundException::of(
            'Controller class "{controller}" does not exist.',
            ['controller' => '']
        );

        // Assert
        $expectedMessage = 'Controller class "" does not exist.';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle empty controller name');
    }

    /**
     * Test ControllerNotFoundException of handles namespaced controller.
     */
    public function testControllerNotFoundExceptionOfHandlesNamespacedController(): void
    {
        // Arrange & Act
        $exception = ControllerNotFoundException::of(
            'Controller class "{controller}" does not exist.',
            ['controller' => 'App\\Http\\Controller\\Admin\\UserController']
        );

        // Assert
        $expectedMessage = 'Controller class "App\\Http\\Controller\\Admin\\UserController" does not exist.';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle namespaced controller names');
    }

    /**
     * Test ControllerNotFoundException getJson returns correct format.
     */
    public function testControllerNotFoundExceptionGetJsonReturnsCorrectFormat(): void
    {
        // Arrange & Act
        $exception = ControllerNotFoundException::of(
            'Controller class "{controller}" does not exist.',
            ['controller' => 'ApiController']
        );
        $json = $exception->getJson();

        // Assert
        $this->assertIsArray($json, 'getJson should return array');
        $this->assertArrayHasKey('code', $json, 'JSON should have code field');
        $this->assertArrayHasKey('msg', $json, 'JSON should have msg field');
        $this->assertSame(404, $json['code'], 'JSON code should be 404');
        $this->assertStringContainsString('Controller class "ApiController" does not exist', $json['msg'], 'JSON msg should contain controller details');
    }
}
