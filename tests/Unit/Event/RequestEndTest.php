<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Http\Event\RequestEnd;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use JsonSerializable;

#[AllowMockObjectsWithoutExpectations]
class RequestEndTest extends TestCase
{
    /**
     * Test RequestEnd event constructor and properties.
     */
    public function testConstructorSetsProperties(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Act
        $event = new RequestEnd($request, $response);

        // Assert
        $this->assertSame($request, $event->request);
        $this->assertSame($response, $event->response);
    }

    /**
     * Test RequestEnd event jsonSerialize method.
     */
    public function testJsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $request->expects($this->once())
            ->method('path')
            ->willReturn('/api/users');

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getHeader')
            ->with('Content-Type')
            ->willReturn('application/json');

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('{"users": []}');

        $event = new RequestEnd($request, $response);

        // Act
        $result = $event->jsonSerialize();

        // Assert
        $expected = [
            'uri' => '/api/users',
            'http_code' => 200,
            'content-type' => 'application/json',
            'content-length' => 13, // strlen('{"users": []}')
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test RequestEnd event handles null content.
     */
    public function testJsonSerializeHandlesNullContent(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $request->expects($this->once())
            ->method('path')
            ->willReturn('/api/status');

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(204);

        $response->expects($this->once())
            ->method('getHeader')
            ->with('Content-Type')
            ->willReturn('');

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(null);

        $event = new RequestEnd($request, $response);

        // Act
        $result = $event->jsonSerialize();

        // Assert
        $expected = [
            'uri' => '/api/status',
            'http_code' => 204,
            'content-type' => '',
            'content-length' => 0, // strlen('') for null content
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test RequestEnd event implements JsonSerializable.
     */
    public function testImplementsJsonSerializable(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $event = new RequestEnd($request, $response);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event);
    }
}
