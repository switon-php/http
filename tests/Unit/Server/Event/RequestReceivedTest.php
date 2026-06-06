<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Event;

use Switon\Http\Event\RequestReceived;
use Switon\Http\Tests\TestCase;
use JsonSerializable;

/**
 * Test cases for RequestReceived event.
 *
 * Tests request received event functionality.
 */
class RequestReceivedTest extends TestCase
{
    /**
     * Test RequestReceived can be instantiated with request data.
     */
    public function testRequestReceivedCanBeInstantiatedWithRequestData(): void
    {
        // Arrange
        $get = ['page' => '1'];
        $post = ['name' => 'John'];
        $server = ['REQUEST_METHOD' => 'POST'];
        $rawBody = '{"data":"test"}';
        $cookie = ['session' => 'abc123'];
        $files = ['upload' => ['name' => 'file.txt']];

        // Act
        $event = new RequestReceived($get, $post, $server, $rawBody, $cookie, $files);

        // Assert
        $this->assertSame($get, $event->GET, 'RequestReceived should store GET data');
        $this->assertSame($post, $event->POST, 'RequestReceived should store POST data');
        $this->assertSame($server, $event->SERVER, 'RequestReceived should store SERVER data');
        $this->assertSame($rawBody, $event->RAW_BODY, 'RequestReceived should store raw body');
        $this->assertSame($cookie, $event->COOKIE, 'RequestReceived should store COOKIE data');
        $this->assertSame($files, $event->FILES, 'RequestReceived should store FILES data');
    }

    /**
     * Test RequestReceived implements JsonSerializable.
     */
    public function testRequestReceivedImplementsJsonSerializable(): void
    {
        // Arrange
        $event = new RequestReceived([], [], [], null, [], []);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event, 'RequestReceived should implement JsonSerializable');
    }

    /**
     * Test RequestReceived jsonSerialize returns correct data.
     */
    public function testRequestReceivedJsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $get = ['q' => 'search'];
        $post = ['username' => 'john', 'password' => 'secret'];
        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/login',
            'QUERY_STRING' => 'q=search',
            'CONTENT_TYPE' => 'application/json',
        ];
        $rawBody = '{"username":"john","password":"secret"}';
        $cookie = ['session_id' => 'xyz789'];
        $files = ['avatar' => ['name' => 'photo.jpg', 'size' => 1024]];

        $event = new RequestReceived($get, $post, $server, $rawBody, $cookie, $files);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertIsArray($data, 'jsonSerialize should return array');
        $this->assertArrayHasKey('method', $data, 'Data should contain method');
        $this->assertArrayHasKey('uri', $data, 'Data should contain uri');
        $this->assertArrayHasKey('query', $data, 'Data should contain query');
        $this->assertArrayHasKey('has_post', $data, 'Data should contain has_post');
        $this->assertArrayHasKey('has_files', $data, 'Data should contain has_files');
        $this->assertArrayHasKey('has_cookie', $data, 'Data should contain has_cookie');
        $this->assertArrayHasKey('content_type', $data, 'Data should contain content_type');

        $this->assertSame('POST', $data['method'], 'Method should match REQUEST_METHOD');
        $this->assertSame('/api/login', $data['uri'], 'URI should match REQUEST_URI');
        $this->assertSame('q=search', $data['query'], 'Query should match QUERY_STRING');
        $this->assertTrue($data['has_post'], 'has_post should be true when POST data exists');
        $this->assertTrue($data['has_files'], 'has_files should be true when FILES data exists');
        $this->assertTrue($data['has_cookie'], 'has_cookie should be true when COOKIE data exists');
        $this->assertSame('application/json', $data['content_type'], 'Content-Type should match');
    }

    /**
     * Test RequestReceived jsonSerialize handles empty data.
     */
    public function testRequestReceivedJsonSerializeHandlesEmptyData(): void
    {
        // Arrange
        $event = new RequestReceived([], [], [], null, [], []);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertSame('', $data['method'], 'Method should be empty string when not set');
        $this->assertSame('', $data['uri'], 'URI should be empty string when not set');
        $this->assertSame('', $data['query'], 'Query should be empty string when not set');
        $this->assertFalse($data['has_post'], 'has_post should be false when POST is empty');
        $this->assertFalse($data['has_files'], 'has_files should be false when FILES is empty');
        $this->assertFalse($data['has_cookie'], 'has_cookie should be false when COOKIE is empty');
        $this->assertSame('', $data['content_type'], 'Content-Type should be empty string when not set');
    }

    /**
     * Test RequestReceived jsonSerialize with GET request.
     */
    public function testRequestReceivedJsonSerializeWithGetRequest(): void
    {
        // Arrange
        $get = ['page' => '1', 'limit' => '10'];
        $server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/users?page=1&limit=10',
            'QUERY_STRING' => 'page=1&limit=10',
        ];

        $event = new RequestReceived($get, [], $server, null, [], []);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertSame('GET', $data['method'], 'Method should be GET');
        $this->assertSame('/api/users?page=1&limit=10', $data['uri'], 'URI should include query string');
        $this->assertSame('page=1&limit=10', $data['query'], 'Query should match');
        $this->assertFalse($data['has_post'], 'has_post should be false for GET request');
        $this->assertFalse($data['has_files'], 'has_files should be false');
        $this->assertFalse($data['has_cookie'], 'has_cookie should be false');
    }

    /**
     * Test RequestReceived jsonSerialize with file upload.
     */
    public function testRequestReceivedJsonSerializeWithFileUpload(): void
    {
        // Arrange
        $post = ['title' => 'My Document'];
        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/upload',
            'CONTENT_TYPE' => 'multipart/form-data',
        ];
        $files = [
            'document' => [
                'name' => 'report.pdf',
                'type' => 'application/pdf',
                'size' => 2048,
                'tmp_name' => '/tmp/php123',
            ],
        ];

        $event = new RequestReceived([], $post, $server, null, [], $files);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertSame('POST', $data['method'], 'Method should be POST');
        $this->assertSame('/upload', $data['uri'], 'URI should be /upload');
        $this->assertTrue($data['has_post'], 'has_post should be true');
        $this->assertTrue($data['has_files'], 'has_files should be true for file upload');
        $this->assertSame('multipart/form-data', $data['content_type'], 'Content-Type should be multipart/form-data');
    }

    /**
     * Test RequestReceived with null raw body.
     */
    public function testRequestReceivedWithNullRawBody(): void
    {
        // Arrange & Act
        $event = new RequestReceived([], [], [], null, [], []);

        // Assert
        $this->assertNull($event->RAW_BODY, 'RAW_BODY should be null when not provided');
    }

    /**
     * Test RequestReceived with empty raw body.
     */
    public function testRequestReceivedWithEmptyRawBody(): void
    {
        // Arrange & Act
        $event = new RequestReceived([], [], [], '', [], []);

        // Assert
        $this->assertSame('', $event->RAW_BODY, 'RAW_BODY should be empty string when provided as empty');
    }
}
