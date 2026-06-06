<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Event;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Switon\Http\Server\Event\ServerStart;
use Switon\Http\Tests\TestCase;
use Swoole\Http\Server;
use JsonSerializable;

/**
 * Test cases for ServerStart event.
 *
 * Tests server start event functionality.
 */
#[AllowMockObjectsWithoutExpectations]
#[RequiresPhpExtension('swoole')]
class ServerStartTest extends TestCase
{
    /**
     * Test ServerStart can be instantiated with server.
     */
    public function testServerStartCanBeInstantiatedWithServer(): void
    {
        // Arrange
        $server = $this->createMock(Server::class);

        // Act
        $event = new ServerStart($server);

        // Assert
        $this->assertSame($server, $event->server, 'ServerStart should store server');
    }

    /**
     * Test ServerStart implements JsonSerializable.
     */
    public function testServerStartImplementsJsonSerializable(): void
    {
        // Arrange
        $server = $this->createMock(Server::class);
        $event = new ServerStart($server);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event, 'ServerStart should implement JsonSerializable');
    }

    /**
     * Test ServerStart jsonSerialize returns correct data.
     */
    public function testServerStartJsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $server = $this->createMock(Server::class);
        $server->host = '127.0.0.1';
        $server->port = 9501;
        $server->mode = 2; // SWOOLE_PROCESS
        $server->setting = ['worker_num' => 4, 'max_request' => 1000];

        $event = new ServerStart($server);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertIsArray($data, 'jsonSerialize should return array');
        $this->assertArrayHasKey('host', $data, 'Data should contain host');
        $this->assertArrayHasKey('port', $data, 'Data should contain port');
        $this->assertArrayHasKey('mode', $data, 'Data should contain mode');
        $this->assertArrayHasKey('settings', $data, 'Data should contain settings');

        $this->assertSame('127.0.0.1', $data['host'], 'Host should match server host');
        $this->assertSame(9501, $data['port'], 'Port should match server port');
        $this->assertSame(2, $data['mode'], 'Mode should match server mode');
        $this->assertSame(['worker_num' => 4, 'max_request' => 1000], $data['settings'], 'Settings should match server settings');
    }

    /**
     * Test ServerStart jsonSerialize handles empty settings.
     */
    public function testServerStartJsonSerializeHandlesEmptySettings(): void
    {
        // Arrange
        $server = $this->createMock(Server::class);
        $server->host = 'localhost';
        $server->port = 8080;
        $server->mode = 1; // SWOOLE_BASE
        $server->setting = [];

        $event = new ServerStart($server);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertSame('localhost', $data['host'], 'Host should be localhost');
        $this->assertSame(8080, $data['port'], 'Port should be 8080');
        $this->assertSame(1, $data['mode'], 'Mode should be 1');
        $this->assertSame([], $data['settings'], 'Settings should be empty array');
    }

    /**
     * Test ServerStart jsonSerialize with complex settings.
     */
    public function testServerStartJsonSerializeWithComplexSettings(): void
    {
        // Arrange
        $server = $this->createMock(Server::class);
        $server->host = '0.0.0.0';
        $server->port = 443;
        $server->mode = 2;
        $server->setting = [
            'worker_num' => 8,
            'max_request' => 2000,
            'ssl_cert_file' => '/path/to/cert.pem',
            'ssl_key_file' => '/path/to/key.pem',
            'open_ssl' => true,
        ];

        $event = new ServerStart($server);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertSame('0.0.0.0', $data['host'], 'Host should be 0.0.0.0');
        $this->assertSame(443, $data['port'], 'Port should be 443');
        $this->assertArrayHasKey('ssl_cert_file', $data['settings'], 'Settings should contain SSL config');
        $this->assertTrue($data['settings']['open_ssl'], 'SSL should be enabled');
    }
}
