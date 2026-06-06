<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Switon\Http\Event\ControllerResolved;
use JsonSerializable;

class ControllerResolvedTest extends TestCase
{
    /**
     * Test ControllerResolved event constructor and properties.
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

        // Act
        $event = new ControllerResolved($controller, $method);

        // Assert
        $this->assertSame($controller, $event->controller);
        $this->assertSame($method, $event->method);
    }

    /**
     * Test ControllerResolved event jsonSerialize method.
     */
    public function testJsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $controller = new class () {
            public function indexAction(): void
            {
            }
        };
        $method = new ReflectionMethod($controller, 'indexAction');
        $event = new ControllerResolved($controller, $method);

        // Act
        $result = $event->jsonSerialize();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('controller', $result);
        $this->assertArrayHasKey('method', $result);
        $this->assertEquals($controller::class, $result['controller']);
        $this->assertEquals('indexAction', $result['method']);
    }

    /**
     * Test ControllerResolved event implements JsonSerializable.
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
        $event = new ControllerResolved($controller, $method);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event);
    }
}
