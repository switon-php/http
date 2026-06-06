<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Event;

use Switon\Http\Server\Event\ServerReady;
use Switon\Http\Tests\TestCase;
use JsonSerializable;
use stdClass;

/**
 * Test cases for ServerReady event.
 *
 * Tests server ready event functionality.
 */
class ServerReadyTest extends TestCase
{
    /**
     * Test ServerReady can be instantiated with server info.
     */
    public function testServerReadyCanBeInstantiatedWithServerInfo(): void
    {
        // Arrange & Act
        $event = new ServerReady('server', 'localhost', 8080, ['worker_num' => 4]);

        // Assert
        $this->assertSame('server', $event->server, 'ServerReady should store server');
        $this->assertSame('localhost', $event->host, 'ServerReady should store host');
        $this->assertSame(8080, $event->port, 'ServerReady should store port');
        $this->assertSame(['worker_num' => 4], $event->settings, 'ServerReady should store settings');
    }

    /**
     * Test ServerReady implements JsonSerializable.
     */
    public function testServerReadyImplementsJsonSerializable(): void
    {
        // Arrange
        $event = new ServerReady('server', 'localhost', 8080);

        // Act & Assert
        $this->assertInstanceOf(JsonSerializable::class, $event, 'ServerReady should implement JsonSerializable');
    }

    /**
     * Test ServerReady jsonSerialize returns full data when server is not null.
     */
    public function testServerReadyJsonSerializeReturnsFullDataWhenServerIsNotNull(): void
    {
        // Arrange
        $event = new ServerReady('server', '127.0.0.1', 9501, ['worker_num' => 8]);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertIsArray($data, 'jsonSerialize should return array');
        $this->assertArrayHasKey('host', $data, 'Data should contain host');
        $this->assertArrayHasKey('port', $data, 'Data should contain port');
        $this->assertArrayHasKey('settings', $data, 'Data should contain settings');

        $this->assertSame('127.0.0.1', $data['host'], 'Host should match');
        $this->assertSame(9501, $data['port'], 'Port should match');
        $this->assertSame(['worker_num' => 8], $data['settings'], 'Settings should match');
    }

    /**
     * Test ServerReady jsonSerialize returns empty array when server is null.
     */
    public function testServerReadyJsonSerializeReturnsEmptyArrayWhenServerIsNull(): void
    {
        // Arrange
        $event = new ServerReady(null, 'localhost', 8080, ['worker_num' => 4]);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertSame([], $data, 'jsonSerialize should return empty array when server is null');
    }

    /**
     * Test ServerReady with default empty settings.
     */
    public function testServerReadyWithDefaultEmptySettings(): void
    {
        // Arrange & Act
        $event = new ServerReady('server', 'localhost', 3000);

        // Assert
        $this->assertSame([], $event->settings, 'Settings should default to empty array');

        $data = $event->jsonSerialize();
        $this->assertSame([], $data['settings'], 'Serialized settings should be empty array');
    }

    /**
     * Test ServerReady with complex settings.
     */
    public function testServerReadyWithComplexSettings(): void
    {
        // Arrange
        $settings = [
            'worker_num' => 16,
            'max_request' => 5000,
            'document_root' => '/var/www',
            'enable_static_handler' => true,
            'ssl_cert_file' => '/etc/ssl/cert.pem',
        ];

        // Act
        $event = new ServerReady('swoole_server', '0.0.0.0', 443, $settings);

        // Assert
        $data = $event->jsonSerialize();
        $this->assertSame('0.0.0.0', $data['host'], 'Host should be 0.0.0.0');
        $this->assertSame(443, $data['port'], 'Port should be 443');
        $this->assertSame($settings, $data['settings'], 'Settings should match complex configuration');
        $this->assertTrue($data['settings']['enable_static_handler'], 'Static handler should be enabled');
    }

    /**
     * Test ServerReady with different server types.
     */
    public function testServerReadyWithDifferentServerTypes(): void
    {
        // Arrange & Act
        $swooleEvent = new ServerReady(new stdClass(), 'localhost', 9501);
        $fpmEvent = new ServerReady(null, 'localhost', 80);

        // Assert
        $swooleData = $swooleEvent->jsonSerialize();
        $fpmData = $fpmEvent->jsonSerialize();

        $this->assertNotEmpty($swooleData, 'Swoole event should return data');
        $this->assertEmpty($fpmData, 'FPM event should return empty array');
    }
}
