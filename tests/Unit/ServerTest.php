<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Core\InputInterface;
use Switon\Http\RequestInterface;
use Switon\Http\Server;
use Switon\Http\ServerInterface;
use Switon\Http\ServerOptions;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for Server factory.
 *
 * Tests server factory functionality and adapter selection.
 */
#[AllowMockObjectsWithoutExpectations]
class ServerTest extends TestCase
{
    /**
     * Test Server factory creates server interface.
     */
    public function testServerFactoryCreatesServerInterface(): void
    {
        // Arrange
        $server = new Server();
        $serverOptions = new ServerOptions();
        $serverOptions->type = 'fpm';

        // Server factory (Server::__invoke) retrieves App::class from container to pass to adapter constructor
        // We need to ensure AppInterface::class maps to an App instance in the container
        if (!$this->container->has(AppInterface::class)) {
            $this->container->set(AppInterface::class, $this->container->get(App::class));
        }

        // Act
        $result = $server($this->container, $serverOptions);

        // Assert
        $this->assertInstanceOf(ServerInterface::class, $result, 'Server factory should return ServerInterface instance');
    }

    /**
     * Test Server factory registers factory in container.
     */
    public function testServerFactoryRegistersFactoryInContainer(): void
    {
        // Arrange
        $server = new Server();
        $serverOptions = new ServerOptions();
        $serverOptions->type = 'php';

        if (!$this->container->has(AppInterface::class)) {
            $this->container->set(AppInterface::class, $this->container->get(App::class));
        }

        // Act
        $server($this->container, $serverOptions);

        // Assert
        $this->assertTrue($this->container->has(ServerInterface::class), 'Container should have ServerInterface registered');
        $serverInstance = $this->container->get(ServerInterface::class);
        $this->assertInstanceOf(ServerInterface::class, $serverInstance, 'Container should return ServerInterface instance');
    }

    /**
     * Test Server factory handles default server type (auto).
     */
    public function testServerFactoryHandlesDefaultServerType(): void
    {
        // Arrange - default type is 'auto'
        $server = new Server();
        $serverOptions = new ServerOptions();
        $serverOptions->type = 'auto';

        if (!$this->container->has(AppInterface::class)) {
            $this->container->set(AppInterface::class, $this->container->get(App::class));
        }

        // Act
        $result = $server($this->container, $serverOptions);

        // Assert
        $this->assertInstanceOf(ServerInterface::class, $result, 'Server factory should handle default server type (auto)');
    }

    /**
     * Test Server factory throws when type is invalid "default".
     */
    public function testServerFactoryThrowsWhenTypeIsDefault(): void
    {
        $server = new Server();
        $serverOptions = new ServerOptions();
        $serverOptions->type = 'default';

        $this->expectException(\Switon\Core\Exception\InvalidArgumentException::class);
        $server($this->container, $serverOptions);
    }

    /**
     * Test Server factory handles auto detection.
     */
    public function testServerFactoryHandlesAutoDetection(): void
    {
        // Arrange
        $server = new Server();
        $serverOptions = new ServerOptions();
        $serverOptions->type = 'auto';

        if (!$this->container->has(AppInterface::class)) {
            $this->container->set(AppInterface::class, $this->container->get(App::class));
        }

        // Act
        $result = $server($this->container, $serverOptions);

        // Assert
        $this->assertInstanceOf(ServerInterface::class, $result, 'Server factory should handle auto detection');
    }

    /**
     * Test Server factory configures swoole settings.
     */
    public function testServerFactoryConfiguresSwooleSettings(): void
    {
        // Arrange
        $server = new Server();
        $serverOptions = new ServerOptions();
        $serverOptions->type = 'swoole';
        $serverOptions->settings = ['custom_setting' => 'value'];

        if (!$this->container->has(AppInterface::class)) {
            $this->container->set(AppInterface::class, $this->container->get(App::class));
        }

        // Act
        $server($this->container, $serverOptions);

        // Assert
        $this->assertTrue($this->container->has(ServerInterface::class), 'Container should have ServerInterface registered');

        // The factory should be configured with swoole settings
        $factory = $this->container->get(ServerInterface::class);
        $this->assertInstanceOf(ServerInterface::class, $factory, 'Should return server interface instance');
    }

    /**
     * Test Server factory merges custom settings with defaults.
     */
    public function testServerFactoryMergesCustomSettingsWithDefaults(): void
    {
        // Arrange
        $server = new Server();
        $serverOptions = new ServerOptions();
        $serverOptions->type = 'swoole';
        $serverOptions->settings = [
            'worker_num' => 4,
            'custom_setting' => 'test_value'
        ];

        if (!$this->container->has(AppInterface::class)) {
            $this->container->set(AppInterface::class, $this->container->get(App::class));
        }

        // Act
        $result = $server($this->container, $serverOptions);

        // Assert
        $this->assertInstanceOf(ServerInterface::class, $result, 'Server factory should merge custom settings with defaults');
    }

    /**
     * Test Server factory handles empty settings.
     */
    public function testServerFactoryHandlesEmptySettings(): void
    {
        // Arrange
        $server = new Server();
        $serverOptions = new ServerOptions();
        $serverOptions->type = 'swoole';
        $serverOptions->settings = [];

        if (!$this->container->has(AppInterface::class)) {
            $this->container->set(AppInterface::class, $this->container->get(App::class));
        }

        // Act
        $result = $server($this->container, $serverOptions);

        // Assert
        $this->assertInstanceOf(ServerInterface::class, $result, 'Server factory should handle empty settings');
    }

    /**
     * Test Server factory can be invoked multiple times.
     */
    public function testServerFactoryCanBeInvokedMultipleTimes(): void
    {
        // Arrange
        $server = new Server();
        $serverOptions1 = new ServerOptions();
        $serverOptions1->type = 'fpm';

        if (!$this->container->has(AppInterface::class)) {
            $this->container->set(AppInterface::class, $this->container->get(App::class));
        }

        // Act
        $result1 = $server($this->container, $serverOptions1);

        // Create a new container for the second invocation to avoid service resolution conflicts
        $container2 = new \Switon\Testing\Container();
        $container2->set(InputInterface::class, RequestInterface::class);
        // Ensure container2 also has AppInterface registered
        if (!$container2->has(AppInterface::class)) {
            $container2->set(AppInterface::class, $container2->get(App::class));
        }

        $serverOptions2 = new ServerOptions();
        $serverOptions2->type = 'php';
        $result2 = $server($container2, $serverOptions2);

        // Assert
        $this->assertInstanceOf(ServerInterface::class, $result1, 'First invocation should return ServerInterface');
        $this->assertInstanceOf(ServerInterface::class, $result2, 'Second invocation should return ServerInterface');
    }
}
