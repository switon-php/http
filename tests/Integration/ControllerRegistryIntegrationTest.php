<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Integration;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ClassScanner;
use Switon\Core\ClassScannerInterface;
use Switon\Core\PathAlias;
use Switon\Core\PathAliasInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\ControllerScannerInterface;

use function array_diff;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ControllerRegistryIntegrationTest extends TestCase
{
    #[Autowired] protected ControllerScannerInterface $registry;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected PathAliasInterface $pathAlias;
    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . '/switon_http_test_' . uniqid('', true);
        mkdir($this->testDir, 0755, true);
        mkdir($this->testDir . '/Controller', 0755, true);

        $this->pathAlias = $this->container->make(PathAlias::class);
        $this->pathAlias->set('@app', $this->testDir);
        $this->container->replace(PathAlias::class, $this->pathAlias);
        $this->container->replace(PathAliasInterface::class, $this->pathAlias);

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->container->remove(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $this->container->replace(\Psr\EventDispatcher\EventDispatcherInterface::class, $this->eventDispatcher);
        // Also remove Switon-specific interface mapping if it exists
        $this->container->remove(\Switon\Eventing\EventDispatcherInterface::class);

        $this->container->remove(ClassScannerInterface::class);
        $this->container->remove(ControllerScannerInterface::class);
        $this->container->replace(ClassScannerInterface::class, ClassScanner::class);
        $this->registry = $this->container->get(ControllerScannerInterface::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->testDir) && is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }

        parent::tearDown();
    }


    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testRegistryIsInstantiated(): void
    {
        $this->assertInstanceOf(ControllerScannerInterface::class, $this->registry);
    }
}
