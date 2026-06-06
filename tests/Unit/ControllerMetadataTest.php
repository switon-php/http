<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Http\ControllerMetadata;
use Switon\Http\Exception\ActionNotFoundException;
use Switon\Routing\Attribute\GetMapping;
use Switon\Routing\Attribute\RequestMapping;

final class ControllerMetadataTest extends TestCase
{
    private ControllerMetadata $metadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metadata = new ControllerMetadata();
    }

    public function testGetPathUsesFirstEntryWhenRequestMappingPathIsArray(): void
    {
        $controller = new #[RequestMapping(['/v2/a', '/v2/b'])] class {
            #[GetMapping('/x')]
            public function indexAction(): void
            {
            }
        };

        $this->assertSame('/v2/a', $this->metadata->getPath($controller::class, 'index'));
    }

    public function testGetPathForAreaIndexControllerAndIndexActionIsRoot(): void
    {
        $fqcn = 'App\\Areas\\Index\\Controller\\IndexController';
        if (!class_exists($fqcn)) {
            eval(<<<'PHP'
namespace App\Areas\Index\Controller;

use Switon\Routing\Attribute\GetMapping;
use Switon\Routing\Attribute\RequestMapping;

#[RequestMapping]
class IndexController
{
    #[GetMapping('/home')]
    public function indexAction(): void
    {
    }
}
PHP);
        }

        $this->assertSame('/', $this->metadata->getPath($fqcn, 'index'));
    }

    public function testGetPathForAreaControllerUsesAreaAndControllerSegments(): void
    {
        $fqcn = 'App\\Areas\\Shop\\Controller\\OrderController';
        if (!class_exists($fqcn)) {
            eval(<<<'PHP'
namespace App\Areas\Shop\Controller;

use Switon\Routing\Attribute\GetMapping;
use Switon\Routing\Attribute\RequestMapping;

#[RequestMapping('/shop/order')]
class OrderController
{
    #[GetMapping('/list')]
    public function listAction(): void
    {
    }
}
PHP);
        }

        $this->assertSame('/shop/order/list', $this->metadata->getPath($fqcn, 'list'));
    }

    public function testGetPathResolvesBareActionNameWithoutSuffix(): void
    {
        $controller = new #[RequestMapping('/api/items')] class {
            #[GetMapping('/show')]
            public function show(): void
            {
            }
        };

        $this->assertSame('/api/items/show', $this->metadata->getPath($controller::class, 'show'));
    }

    public function testGetPathThrowsWhenActionMethodMissing(): void
    {
        $controller = new #[RequestMapping('/only')] class {
            public function indexAction(): void
            {
            }
        };

        $this->expectException(ActionNotFoundException::class);
        $this->metadata->getPath($controller::class, 'missing');
    }

    public function testGetActionsReturnsOnlyPublicMethodsWithRouteMappingAttributes(): void
    {
        $controller = new #[RequestMapping('/api/widgets')] class {
            #[GetMapping('/list')]
            public function listAction(): void
            {
            }

            public function internalHelper(): void
            {
            }
        };

        $this->assertSame(['listAction'], $this->metadata->getActions($controller::class));
    }
}
