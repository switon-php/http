<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Http\ControllerMetadata;
use Switon\Http\Exception\InvalidControllerException;
use Switon\Routing\Attribute\GetMapping;
use Switon\Routing\Attribute\RequestMapping;

final class ControllerMetadataCoverageTest extends TestCase
{
    public function testGetActionsIgnoresPublicMethodsWithoutActionMappings(): void
    {
        $controller = new #[RequestMapping('/api')] class {
            #[GetMapping('/index')]
            public function indexAction(): void
            {
            }

            public function helper(): void
            {
            }
        };

        $metadata = new ControllerMetadata();

        $this->assertSame(['indexAction'], $metadata->getActions($controller::class));
    }

    public function testGetPathThrowsForControllerOutsideExpectedDirectoryStructure(): void
    {
        $controller = new #[RequestMapping('')] class {
            #[GetMapping('/index')]
            public function indexAction(): void
            {
            }
        };

        $this->expectException(InvalidControllerException::class);

        (new ControllerMetadata())->getPath($controller::class, 'index');
    }

    public function testBuildPathFromPrefixHandlesEmptyPrefix(): void
    {
        $metadata = new class () extends ControllerMetadata {
            public function build(string $prefix, string $action): string
            {
                return $this->buildPathFromPrefix($prefix, $action);
            }
        };

        $this->assertSame('/', $metadata->build('', 'index'));
        $this->assertSame('/show', $metadata->build('', 'show'));
    }

    public function testBuildPathFromPrefixTrimsTrailingSlash(): void
    {
        $metadata = new class () extends ControllerMetadata {
            public function build(string $prefix, string $action): string
            {
                return $this->buildPathFromPrefix($prefix, $action);
            }
        };

        $this->assertSame('/api/items', $metadata->build('/api/items/', 'index'));
        $this->assertSame('/api/items/show', $metadata->build('/api/items/', 'show'));
    }
}
