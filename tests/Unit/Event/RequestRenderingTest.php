<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Switon\Http\Event\RequestRendering;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use JsonSerializable;

#[AllowMockObjectsWithoutExpectations]
class RequestRenderingTest extends TestCase
{
    /**
     * Test RequestRendering event constructor and properties.
     */
    public function testConstructorSetsProperties(): void
    {
        // Arrange
        $controller = new class () {
            public function testAction(): array
            {
                return ['data' => 'test'];
            }
        };
        $method = new ReflectionMethod($controller, 'testAction');
        $return = ['data' => 'test'];
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $prefix = '/api';

        // Act
        $event = new RequestRendering($method, $return, $request, $response, $prefix);

        // Assert
        $this->assertSame($method, $event->method);
        $this->assertSame($return, $event->return);
        $this->assertSame($request, $event->request);
        $this->assertSame($response, $event->response);
        $this->assertEquals($prefix, $event->prefix);
    }

    /**
     * Test RequestRendering event constructor with default prefix.
     */
    public function testConstructorWithDefaultPrefix(): void
    {
        // Arrange
        $controller = new class () {
            public function testAction(): void
            {
            }
        };
        $method = new ReflectionMethod($controller, 'testAction');
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Act
        $event = new RequestRendering($method, null, $request, $response);

        // Assert
        $this->assertEquals('', $event->prefix);
    }

    /**
     * Test RequestRendering event jsonSerialize method.
     */
    public function testJsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $controller = new class () {
            public function editAction(): void
            {
            }
        };
        $method = new ReflectionMethod($controller, 'editAction');
        $return = ['form' => 'data'];
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $prefix = '/admin';
        $event = new RequestRendering($method, $return, $request, $response, $prefix);

        // Act
        $result = $event->jsonSerialize();

        // Assert
        $expected = [
            'controller' => $controller::class,
            'action' => 'editAction',
            'prefix' => '/admin',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test RequestRendering event implements JsonSerializable.
     */
    public function testImplementsJsonSerializable(): void
    {
        // Arrange
        $controller = new class () {
            public function testAction(): void
            {
            }
        };
        $method = new ReflectionMethod($controller, 'testAction');
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $event = new RequestRendering($method, null, $request, $response);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event);
    }
}
