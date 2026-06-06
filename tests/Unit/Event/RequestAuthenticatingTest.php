<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Http\Event\RequestAuthenticating;
use Switon\Http\RequestInterface;
use JsonSerializable;

#[AllowMockObjectsWithoutExpectations]
class RequestAuthenticatingTest extends TestCase
{
    /**
     * Test RequestAuthenticating event constructor and properties.
     */
    public function testConstructorSetsRequestProperty(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);

        // Act
        $event = new RequestAuthenticating($request);

        // Assert
        $this->assertSame($request, $event->request);
    }

    /**
     * Test RequestAuthenticating event jsonSerialize method.
     */
    public function testJsonSerializeReturnsEmptyArray(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $event = new RequestAuthenticating($request);

        // Act
        $result = $event->jsonSerialize();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test RequestAuthenticating event implements JsonSerializable.
     */
    public function testImplementsJsonSerializable(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $event = new RequestAuthenticating($request);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event);
    }
}
