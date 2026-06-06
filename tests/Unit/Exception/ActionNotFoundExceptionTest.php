<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Exception;

use Switon\Http\Exception\ActionNotFoundException;
use Switon\Http\Exception\NotFoundException;
use Switon\Http\Tests\TestCase;
use ReflectionException;

/**
 * Test cases for ActionNotFoundException.
 *
 * Tests action method not found exception functionality.
 */
class ActionNotFoundExceptionTest extends TestCase
{
    /**
     * Test ActionNotFoundException inherits from NotFoundException.
     */
    public function testActionNotFoundExceptionInheritsFromNotFoundException(): void
    {
        // Arrange & Act
        $exception = ActionNotFoundException::of('Action not found.');

        // Assert
        $this->assertInstanceOf(NotFoundException::class, $exception, 'ActionNotFoundException should inherit from NotFoundException');
        $this->assertSame(404, $exception->getStatusCode(), 'ActionNotFoundException should return 404 status code');
    }

    /**
     * Test ActionNotFoundException of factory creates exception with correct message.
     */
    public function testActionNotFoundExceptionOfFactoryCreatesExceptionWithCorrectMessage(): void
    {
        // Arrange & Act
        $exception = ActionNotFoundException::of(
            'Action method "{action}" does not exist in controller "{controller}".',
            ['action' => 'deleteUser', 'controller' => 'App\\Controller\\UserController']
        );

        // Assert
        $expectedMessage = 'Action method "deleteUser" does not exist in controller "App\\Controller\\UserController".';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should have formatted message');
    }

    /**
     * Test ActionNotFoundException of stores context data.
     */
    public function testActionNotFoundExceptionOfStoresContextData(): void
    {
        // Arrange & Act
        $exception = ActionNotFoundException::of(
            'Action method "{action}" does not exist in controller "{controller}".',
            ['action' => 'updatePost', 'controller' => 'PostController', 'extra' => 'data']
        );

        // Assert
        $context = $exception->getContext();
        $this->assertArrayHasKey('extra', $context, 'Context should contain extra data');
        $this->assertSame('data', $context['extra'], 'Context extra should be preserved');
    }

    /**
     * Test ActionNotFoundException of with previous exception.
     */
    public function testActionNotFoundExceptionOfWithPreviousException(): void
    {
        // Arrange
        $previousException = new ReflectionException('Method not found');

        // Act
        $exception = ActionNotFoundException::of('Action not found.', [], 0, $previousException);

        // Assert
        $this->assertSame($previousException, $exception->getPrevious(), 'Exception should store previous exception');
    }

    /**
     * Test ActionNotFoundException of without previous exception.
     */
    public function testActionNotFoundExceptionOfWithoutPreviousException(): void
    {
        // Arrange & Act
        $exception = ActionNotFoundException::of('Action not found.');

        // Assert
        $this->assertNull($exception->getPrevious(), 'Exception should not have previous exception when not provided');
    }

    /**
     * Test ActionNotFoundException of handles empty strings.
     */
    public function testActionNotFoundExceptionOfHandlesEmptyStrings(): void
    {
        // Arrange & Act
        $exception = ActionNotFoundException::of(
            'Action method "{action}" does not exist in controller "{controller}".',
            ['action' => '', 'controller' => '']
        );

        // Assert
        $expectedMessage = 'Action method "" does not exist in controller "".';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle empty controller and action names');
    }

    /**
     * Test ActionNotFoundException of handles special characters.
     */
    public function testActionNotFoundExceptionOfHandlesSpecialCharacters(): void
    {
        // Arrange & Act
        $exception = ActionNotFoundException::of(
            'Action method "{action}" does not exist in controller "{controller}".',
            ['action' => 'action-with-dash', 'controller' => 'Test\\Controller@Special']
        );

        // Assert
        $expectedMessage = 'Action method "action-with-dash" does not exist in controller "Test\\Controller@Special".';
        $this->assertSame($expectedMessage, $exception->getMessage(), 'Exception should handle special characters in names');
    }

    /**
     * Test ActionNotFoundException getJson returns correct format.
     */
    public function testActionNotFoundExceptionGetJsonReturnsCorrectFormat(): void
    {
        // Arrange & Act
        $exception = ActionNotFoundException::of(
            'Action method "{action}" does not exist in controller "{controller}".',
            ['action' => 'show', 'controller' => 'UserController']
        );
        $json = $exception->getJson();

        // Assert
        $this->assertIsArray($json, 'getJson should return array');
        $this->assertArrayHasKey('code', $json, 'JSON should have code field');
        $this->assertArrayHasKey('msg', $json, 'JSON should have msg field');
        $this->assertSame(404, $json['code'], 'JSON code should be 404');
        $this->assertStringContainsString('Action method "show" does not exist', $json['msg'], 'JSON msg should contain action details');
    }
}
