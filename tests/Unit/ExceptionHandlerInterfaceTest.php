<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use Switon\Http\ExceptionHandlerInterface;
use Throwable;

class ExceptionHandlerInterfaceTest extends BaseTestCase
{
    public function testExceptionHandlerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ExceptionHandlerInterface::class));
        $this->assertTrue(method_exists(ExceptionHandlerInterface::class, 'handle'));
    }

    public function testExceptionHandlerInterfaceHandleMethodSignature(): void
    {
        $reflection = new ReflectionClass(ExceptionHandlerInterface::class);
        $method = $reflection->getMethod('handle');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertContains($returnType->getName(), ['bool', '?bool']);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertSame('throwable', $parameters[0]->getName());
        $this->assertSame(Throwable::class, $parameters[0]->getType()->getName());
    }
}
