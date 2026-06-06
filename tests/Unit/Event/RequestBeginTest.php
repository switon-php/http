<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Http\Event\RequestBegin;
use Switon\Http\RequestInterface;
use JsonSerializable;

#[AllowMockObjectsWithoutExpectations]
class RequestBeginTest extends TestCase
{
    /**
     * Test RequestBegin event constructor and properties.
     */
    public function testConstructorSetsRequestProperty(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);

        // Act
        $event = new RequestBegin($request);

        // Assert
        $this->assertSame($request, $event->request);
    }

    /**
     * Test RequestBegin event jsonSerialize method.
     */
    public function testJsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('verb')
            ->willReturn('GET');
        $request->expects($this->once())
            ->method('url')
            ->willReturn('/api/users');
        $request->expects($this->once())
            ->method('server')
            ->with('QUERY_STRING')
            ->willReturn('page=1&limit=10');
        $request->expects($this->once())
            ->method('ip')
            ->willReturn('192.168.1.100');

        $event = new RequestBegin($request);

        // Act
        $result = $event->jsonSerialize();

        // Assert
        $expected = [
            'method' => 'GET',
            'url' => '/api/users',
            'query' => 'page=1&limit=10',
            'client_ip' => '192.168.1.100',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test RequestBegin event implements JsonSerializable.
     */
    public function testImplementsJsonSerializable(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $event = new RequestBegin($request);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event);
    }
}
