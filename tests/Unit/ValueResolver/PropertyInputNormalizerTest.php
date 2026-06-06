<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\ValueResolver;

use Attribute;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionFunction;
use ReflectionParameter;
use Switon\Http\Attribute\RequestData;
use Switon\Http\RequestDataResolver;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use ReflectionProperty;

/**
 * @group http
 */
#[AllowMockObjectsWithoutExpectations]
class PropertyInputNormalizerTest extends TestCase
{
    public function testRequestDataResolverAppliesPropertyInputNormalizers(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('all')
            ->willReturn(['status' => 'paid', 'title' => 'hello']);

        $this->container->replace(RequestInterface::class, $request);

        $resolver = $this->container->get(RequestDataResolver::class);
        $parameter = $this->createTypedParameter(NormalizedInput::class, 'input');
        $result = $resolver->resolve($parameter, NormalizedInput::class);

        $this->assertSame('PAID', $result->status);
        $this->assertSame('hello', $result->title);
    }

    protected function createTypedParameter(string $typeName, string $paramName): ReflectionParameter
    {
        $fn = eval("return function ({$typeName} \${$paramName}) {}; ");
        return (new ReflectionFunction($fn))->getParameters()[0];
    }
}

#[RequestData]
class NormalizedInput
{
    #[UppercaseInput]
    public string $status = '';

    public string $title = '';
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class UppercaseInput
{
    public function normalizeInput(ReflectionProperty $property, mixed $value): mixed
    {
        return strtoupper((string)$value);
    }
}
