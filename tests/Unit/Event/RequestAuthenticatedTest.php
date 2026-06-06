<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Http\Event\RequestAuthenticated;
use Switon\Http\RequestInterface;
use JsonSerializable;

#[AllowMockObjectsWithoutExpectations]
class RequestAuthenticatedTest extends TestCase
{
    /**
     * Test RequestAuthenticated event constructor and properties.
     */
    public function testConstructorSetsRequestProperty(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);

        // Act
        $event = new RequestAuthenticated($request);

        // Assert
        $this->assertSame($request, $event->request);
    }

    /**
     * Test RequestAuthenticated event jsonSerialize method.
     */
    public function testJsonSerializeReturnsEmptyArray(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $event = new RequestAuthenticated($request);

        // Act
        $result = $event->jsonSerialize();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test RequestAuthenticated event implements JsonSerializable.
     */
    public function testImplementsJsonSerializable(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $event = new RequestAuthenticated($request);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event);
    }
}
