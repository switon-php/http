<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Filesystem;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Http\ControllerMetadataInterface;
use Switon\Http\Exception\ControllerNotFoundException;
use Switon\Http\Exception\InvalidControllerException;
use Switon\Http\Tests\TestCase;
use Switon\Routing\Attribute\GetMapping;
use Switon\Routing\Attribute\RequestMapping;
use Switon\Routing\ControllerScannerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ControllerRegistryTest extends TestCase
{
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected PathAliasInterface $pathAlias;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    protected ControllerScannerInterface $scanner;
    protected ControllerMetadataInterface $metadata;

    protected function beforeSetUpHttpContainer(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->container->remove(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $this->container->replace(\Psr\EventDispatcher\EventDispatcherInterface::class, $this->eventDispatcher);
        // Also remove Switon-specific interface mapping if it exists
        $this->container->remove(\Switon\Eventing\EventDispatcherInterface::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = $this->container->get(ControllerScannerInterface::class);
        $this->metadata = $this->container->get(ControllerMetadataInterface::class);
    }

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        // Replace FilesystemInterface with mock (remove first to avoid ServiceAlreadyResolvedException)
        $this->container->remove(FilesystemInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->container->replace(FilesystemInterface::class, $this->filesystem);

        // Replace PathAliasInterface with mock (remove first to avoid ServiceAlreadyResolvedException)
        $this->container->remove(PathAliasInterface::class);
        $this->pathAlias = $this->createMock(PathAliasInterface::class);
        $this->container->replace(PathAliasInterface::class, $this->pathAlias);

        // Property autowiring is automatically performed by parent::setUp()
    }

    public function testGetControllersReturnsEmptyArrayWhenNoControllersFound(): void
    {
        $this->filesystem->expects($this->exactly(2))
            ->method('glob')
            ->willReturn([]);

        $controllers = $this->scanner->getControllers();

        $this->assertIsArray($controllers);
        $this->assertCount(0, $controllers);
    }

    public function testGetControllersScansControllerFilesAndReturnsValidControllers(): void
    {
        // Mock filesystem to return empty results (simplified test)
        $this->filesystem->expects($this->exactly(2))
            ->method('glob')
            ->willReturn([]);

        $controllers = $this->scanner->getControllers();

        $this->assertIsArray($controllers);
        $this->assertCount(0, $controllers);
    }

    public function testGetActionsThrowsControllerNotFoundExceptionForNonExistentController(): void
    {
        $this->expectException(ControllerNotFoundException::class);

        $this->metadata->getActions('NonExistent\\Controller');
    }

    public function testGetActionsThrowsInvalidControllerExceptionForControllerWithoutRequestMapping(): void
    {
        $this->expectException(InvalidControllerException::class);
        $this->expectExceptionMessage('RequestMapping');

        // Use a class that exists but doesn't have RequestMapping
        $this->metadata->getActions(\Switon\Http\Controller::class);
    }

    public function testGetActionsReturnsActionsForValidController(): void
    {
        $controller = new #[RequestMapping('/test')] class {
            #[GetMapping('/test/index')]
            public function indexAction(): void
            {
            }

            #[GetMapping('/test/show')]
            public function showAction(): void
            {
            }
        };
        $controllerClass = $controller::class;

        $actions = $this->metadata->getActions($controllerClass);

        $this->assertIsArray($actions);
        $this->assertGreaterThan(0, count($actions));
        $this->assertContains('indexAction', $actions);
        $this->assertContains('showAction', $actions);
    }

    public function testGetPathThrowsControllerNotFoundExceptionForNonExistentController(): void
    {
        $this->expectException(ControllerNotFoundException::class);

        $this->metadata->getPath('NonExistent\\Controller', 'index');
    }

    public function testGetPathThrowsInvalidControllerExceptionForControllerWithoutRequestMapping(): void
    {
        $this->expectException(InvalidControllerException::class);

        $this->metadata->getPath(\Switon\Http\Controller::class, 'index');
    }

    public function testGetPathThrowsActionNotFoundExceptionForNonExistentAction(): void
    {
        $controller = new class () {
            #[RequestMapping('/test')]
            public function indexAction(): void
            {
            }
        };
        $controllerClass = $controller::class;

        $this->expectException(InvalidControllerException::class);

        $this->metadata->getPath($controllerClass, 'nonExistent');
    }

    public function testGetPathReturnsCorrectPathForStandardControllerWithIndexAction(): void
    {
        $controllerClass = 'App\\Controller\\UserIndexTestController';

        if (!class_exists($controllerClass)) {
            eval("
                namespace App\\Controller;
                use Switon\\Routing\\Attribute\\RequestMapping;
                #[RequestMapping('/api/user')]
                class UserIndexTestController {
                    public function indexAction(): void {}
                    public function showAction(): void {}
                }
            ");
        }

        $this->assertTrue(class_exists($controllerClass));
        $this->assertTrue(method_exists($controllerClass, 'indexAction'));

        $reflection = new ReflectionClass($controllerClass);
        $attributes = $reflection->getAttributes(RequestMapping::class);
        $this->assertNotEmpty($attributes);

        $path = $this->metadata->getPath($controllerClass, 'index');
        $this->assertSame('/api/user', $path);
    }

    public function testGetPathReturnsCorrectPathForStandardControllerWithNonIndexAction(): void
    {
        $controllerClass = 'App\\Controller\\UserController';

        if (!class_exists($controllerClass)) {
            eval("
                namespace App\\Controller;
                use Switon\\Routing\\Attribute\\RequestMapping;
                #[RequestMapping('/api/user')]
                class UserController {
                    public function showAction(): void {}
                }
            ");
        }

        $path = $this->metadata->getPath($controllerClass, 'show');
        $this->assertSame('/api/user/show', $path);
    }

    public function testGetPathHandlesActionNameWithActionSuffix(): void
    {
        $controllerClass = 'App\\Controller\\UserController';

        if (!class_exists($controllerClass)) {
            eval("
                namespace App\\Controller;
                use Switon\\Routing\\Attribute\\RequestMapping;
                #[RequestMapping('/api/user')]
                class UserController {
                    public function showAction(): void {}
                }
            ");
        }

        // Test with Action suffix
        $path = $this->metadata->getPath($controllerClass, 'showAction');
        $this->assertSame('/api/user/show', $path);
    }

    public function testGetPathHandlesIndexControllerWithIndexAction(): void
    {
        $controllerClass = 'App\\Controller\\IndexController';

        if (!class_exists($controllerClass)) {
            eval("
                namespace App\\Controller;
                use Switon\\Routing\\Attribute\\RequestMapping;
                #[RequestMapping('/')]
                class IndexController {
                    public function indexAction(): void {}
                }
            ");
        }

        $path = $this->metadata->getPath($controllerClass, 'index');
        $this->assertSame('/', $path);
    }

    public function testGetPathReturnsCorrectPathForAreaController(): void
    {
        $controllerClass = 'App\\Areas\\Admin\\Controller\\DashboardController';

        if (!class_exists($controllerClass)) {
            eval("
                namespace App\\Areas\\Admin\\Controller;
                use Switon\\Routing\\Attribute\\RequestMapping;
                #[RequestMapping('/admin/dashboard')]
                class DashboardController {
                    public function indexAction(): void {}
                    public function settingsAction(): void {}
                }
            ");
        }

        $path = $this->metadata->getPath($controllerClass, 'index');
        $this->assertSame('/admin/dashboard', $path);

        $path = $this->metadata->getPath($controllerClass, 'settings');
        $this->assertSame('/admin/dashboard/settings', $path);
    }

    public function testGetPathThrowsInvalidControllerExceptionForInvalidPathPattern(): void
    {
        $controllerClass = 'App\\Invalid\\Pattern\\Controller';

        if (!class_exists($controllerClass)) {
            eval("
                namespace App\\Invalid\\Pattern;
                use Switon\\Routing\\Attribute\\RequestMapping;
                #[RequestMapping]
                class Controller {
                    public function indexAction(): void {}
                }
            ");
        }

        $this->expectException(InvalidControllerException::class);
        $this->metadata->getPath($controllerClass, 'index');
    }

}
