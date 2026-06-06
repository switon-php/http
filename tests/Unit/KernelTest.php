<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\InputInterface;
use Switon\Http\Kernel;
use Switon\Http\RequestInterface;
use ReflectionClass;

class KernelTest extends TestCase
{
    public function testKernelServicesBindInputToRequest(): void
    {
        $kernel = new Kernel(__DIR__);

        $reflection = new ReflectionClass(Kernel::class);
        $property = $reflection->getProperty('services');

        $this->assertSame(
            [InputInterface::class => RequestInterface::class],
            $property->getValue($kernel)
        );
    }
}
