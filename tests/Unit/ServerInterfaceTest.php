<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use Switon\Http\ServerInterface;

class ServerInterfaceTest extends BaseTestCase
{
    public function testServerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ServerInterface::class));
        $this->assertTrue(method_exists(ServerInterface::class, 'start'));
        $this->assertTrue(method_exists(ServerInterface::class, 'sendHeaders'));
        $this->assertTrue(method_exists(ServerInterface::class, 'sendBody'));
        $this->assertTrue(method_exists(ServerInterface::class, 'write'));
    }

    public function testServerInterfaceStartMethodSignature(): void
    {
        $reflection = new ReflectionClass(ServerInterface::class);
        $method = $reflection->getMethod('start');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertSame('void', $returnType->getName());
        $this->assertCount(0, $method->getParameters());
    }

    public function testServerInterfaceSendHeadersMethodSignature(): void
    {
        $reflection = new ReflectionClass(ServerInterface::class);
        $method = $reflection->getMethod('sendHeaders');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertSame('void', $returnType->getName());
        $this->assertCount(0, $method->getParameters());
    }

    public function testServerInterfaceSendBodyMethodSignature(): void
    {
        $reflection = new ReflectionClass(ServerInterface::class);
        $method = $reflection->getMethod('sendBody');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertSame('void', $returnType->getName());
        $this->assertCount(0, $method->getParameters());
    }

    public function testServerInterfaceWriteMethodSignature(): void
    {
        $reflection = new ReflectionClass(ServerInterface::class);
        $method = $reflection->getMethod('write');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertContains($returnType->getName(), ['bool', '?bool']);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertSame('chunk', $parameters[0]->getName());
        $this->assertTrue($parameters[0]->getType() !== null);
    }
}
