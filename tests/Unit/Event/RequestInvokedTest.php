<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Switon\Http\Event\RequestInvoked;
use JsonSerializable;

class RequestInvokedTest extends TestCase
{
    /**
     * Test RequestInvoked event constructor and properties.
     */
    public function testConstructorSetsProperties(): void
    {
        // Arrange
        $controller = new class () {
            public function testAction(): string
            {
                return 'test result';
            }
        };
        $method = new ReflectionMethod($controller, 'testAction');
        $return = 'test result';

        // Act
        $event = new RequestInvoked($method, $return);

        // Assert
        $this->assertSame($method, $event->method);
        $this->assertSame($return, $event->return);
        $this->assertEquals($controller::class, $event->controller);
        $this->assertEquals('testAction', $event->action);
    }

    /**
     * Test RequestInvoked event jsonSerialize method.
     */
    public function testJsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $controller = new class () {
            public function indexAction(): array
            {
                return ['data' => 'test'];
            }
        };
        $method = new ReflectionMethod($controller, 'indexAction');
        $return = ['data' => 'test'];
        $event = new RequestInvoked($method, $return);

        // Act
        $result = $event->jsonSerialize();

        // Assert
        $expected = [
            'controller' => $controller::class,
            'action' => 'indexAction',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test RequestInvoked event implements JsonSerializable.
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
        $event = new RequestInvoked($method, null);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event);
    }
}
