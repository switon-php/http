<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Switon\Http\Event\RequestRendered;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use JsonSerializable;

#[AllowMockObjectsWithoutExpectations]
class RequestRenderedTest extends TestCase
{
    /**
     * Test RequestRendered event constructor and properties.
     */
    public function testConstructorSetsProperties(): void
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
        $event = new RequestRendered($method, $request, $response);

        // Assert
        $this->assertSame($method, $event->method);
        $this->assertSame($request, $event->request);
        $this->assertSame($response, $event->response);
    }

    /**
     * Test RequestRendered event jsonSerialize method.
     */
    public function testJsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $controller = new class () {
            public function showAction(): void
            {
            }
        };
        $method = new ReflectionMethod($controller, 'showAction');
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $event = new RequestRendered($method, $request, $response);

        // Act
        $result = $event->jsonSerialize();

        // Assert
        $expected = [
            'controller' => $controller::class,
            'action' => 'showAction',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test RequestRendered event implements JsonSerializable.
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
        $event = new RequestRendered($method, $request, $response);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event);
    }
}
