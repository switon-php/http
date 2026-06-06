<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\ValueResolver;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionFunction;
use ReflectionParameter;
use Switon\Http\Attribute\RequestBody;
use Switon\Http\Attribute\RequestData;
use Switon\Http\Attribute\RequestQuery;
use Switon\Http\RequestBodyResolver;
use Switon\Http\RequestDataResolver;
use Switon\Http\RequestInterface;
use Switon\Http\RequestQueryResolver;
use Switon\Http\Tests\TestCase;
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;

/**
 * @group http
 */
#[AllowMockObjectsWithoutExpectations]
class RequestSourceResolverTest extends TestCase
{
    public function testRequestDataUsesMergedInput(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);

        $this->container->replace(RequestInterface::class, $request);
        $this->container->replace(ValidatorInterface::class, $validator);
        $resolver = $this->make(RequestDataResolver::class);

        $source = ['name' => 'from-all'];
        $validation = $this->createValidationMock();

        $request->expects($this->once())->method('all')->willReturn($source);
        $validator->expects($this->once())->method('beginValidate')->with($source)->willReturn($validation);
        $validator->expects($this->once())->method('endValidate')->with($validation);
        $validation->method('validate')->willReturn(true);

        $parameter = $this->createTypedParameter(RequestDataInput::class, 'request');
        $result = $resolver->resolve($parameter, $parameter->getType()->getName());

        $this->assertInstanceOf(RequestDataInput::class, $result);
        $this->assertSame('from-all', $result->name);
    }

    public function testRequestBodyUsesPostInputWhenRequestAvailable(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);

        $this->container->replace(RequestInterface::class, $request);
        $this->container->replace(ValidatorInterface::class, $validator);
        $resolver = $this->make(RequestBodyResolver::class);

        $source = ['name' => 'from-post'];
        $validation = $this->createValidationMock();

        $request->expects($this->once())->method('post')->willReturn($source);
        $validator->expects($this->once())->method('beginValidate')->with($source)->willReturn($validation);
        $validator->expects($this->once())->method('endValidate')->with($validation);
        $validation->method('validate')->willReturn(true);

        $parameter = $this->createTypedParameter(RequestBodyInput::class, 'request');
        $result = $resolver->resolve($parameter, $parameter->getType()->getName());

        $this->assertInstanceOf(RequestBodyInput::class, $result);
        $this->assertSame('from-post', $result->name);
    }

    public function testRequestQueryUsesQueryInputWhenRequestAvailable(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);

        $this->container->replace(RequestInterface::class, $request);
        $this->container->replace(ValidatorInterface::class, $validator);
        $resolver = $this->make(RequestQueryResolver::class);

        $source = ['name' => 'from-query'];
        $validation = $this->createValidationMock();

        $request->expects($this->once())->method('query')->willReturn($source);
        $validator->expects($this->once())->method('beginValidate')->with($source)->willReturn($validation);
        $validator->expects($this->once())->method('endValidate')->with($validation);
        $validation->method('validate')->willReturn(true);

        $parameter = $this->createTypedParameter(RequestQueryInput::class, 'request');
        $result = $resolver->resolve($parameter, $parameter->getType()->getName());

        $this->assertInstanceOf(RequestQueryInput::class, $result);
        $this->assertSame('from-query', $result->name);
    }

    protected function createValidationMock(): Validation
    {
        $validation = $this->createMock(Validation::class);
        $validation->field = '';
        $validation->value = null;
        return $validation;
    }

    protected function createTypedParameter(string $typeName, string $paramName): ReflectionParameter
    {
        $fn = eval("return function ({$typeName} \${$paramName}) {}; ");
        return (new ReflectionFunction($fn))->getParameters()[0];
    }
}

#[RequestData]
class RequestDataInput
{
    public string $name = '';
}

#[RequestBody]
class RequestBodyInput
{
    public string $name = '';
}

#[RequestQuery]
class RequestQueryInput
{
    public string $name = '';
}
