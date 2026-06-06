<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\ValueResolver;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionFunction;
use ReflectionParameter;
use Switon\Binding\InputBinder;
use Switon\Http\Attribute\RequestBody as RequestBodyAttribute;
use Switon\Http\RequestBodyResolver;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use Switon\Validating\Attribute\Length;
use Switon\Validating\Attribute\Required;
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;

/**
 * Coverage for nullable scalar handling when request payload explicitly provides null.
 *
 * @group http
 * @group nested-input
 */
#[AllowMockObjectsWithoutExpectations]
class NullableScalarNullTest extends TestCase
{
    protected RequestBodyResolver $resolver;
    protected RequestInterface $request;
    protected ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(RequestInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $inputBinder = $this->container->make(InputBinder::class, ['validator' => $this->validator]);

        $this->resolver = new class ($this->request, $inputBinder) extends RequestBodyResolver {
            public function __construct(
                RequestInterface $request,
                InputBinder $inputBinder,
            ) {
                $this->request = $request;
                $this->inputBinder = $inputBinder;
            }
        };
    }

    protected function createValidation(array $source): Validation
    {
        $validation = new Validation($this->validator, $source);

        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        return $validation;
    }

    public function testNullableScalarNullDoesNotTriggerTypeError(): void
    {
        $inputData = ['email' => null];
        $this->request->method('post')->willReturn($inputData);

        $validation = $this->createValidation($inputData);

        $parameter = $this->createMockParameter(TestNullableScalarInput::class, 'request');
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        $this->assertEmpty($validation->getErrors());
        $this->assertNull($result->email);
    }

    public function testNullableScalarExplicitRequiredNullProducesRequiredError(): void
    {
        $inputData = ['email' => null];
        $this->request->method('post')->willReturn($inputData);

        $validation = $this->createValidation($inputData);

        $parameter = $this->createMockParameter(TestNullableScalarExplicitRequiredInput::class, 'request');
        $this->resolver->resolve($parameter, $parameter->getType()->getName());

        $errors = $validation->getErrors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('need email', $errors['email']);
    }

    public function testNullableScalarSkipsOtherConstraintsOnNullWhenNotRequired(): void
    {
        $inputData = ['name' => null];
        $this->request->method('post')->willReturn($inputData);

        $validation = $this->createValidation($inputData);

        $parameter = $this->createMockParameter(TestNullableScalarWithLengthInput::class, 'request');
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        $this->assertEmpty($validation->getErrors());
        $this->assertNull($result->name);
    }

    public function testNonNullableScalarNullIsTreatedAsMissingAndProducesRequiredError(): void
    {
        $inputData = ['email' => null];
        $this->request->method('post')->willReturn($inputData);

        $validation = $this->createValidation($inputData);

        $parameter = $this->createMockParameter(TestNonNullableScalarInput::class, 'request');
        $this->resolver->resolve($parameter, $parameter->getType()->getName());

        $errors = $validation->getErrors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertNotEmpty($errors['email']);
    }

    protected function createMockParameter(string $typeName, string $paramName = 'request'): ReflectionParameter
    {
        $fn = eval("return function ({$typeName} \${$paramName}) {}; ");
        return (new ReflectionFunction($fn))->getParameters()[0];
    }

    protected function formatMessage(string $message, array $placeholders): string
    {
        return str_replace(
            array_map(fn ($key) => "{{$key}}", array_keys($placeholders)),
            array_map(
                fn ($value) => is_scalar($value) || $value === null ? (string)$value : (json_encode($value) ?: ''),
                array_values($placeholders)
            ),
            $message
        );
    }
}

#[RequestBodyAttribute]
class TestNullableScalarInput
{
    public ?string $email;
}

#[RequestBodyAttribute]
class TestNullableScalarExplicitRequiredInput
{
    #[Required(message: 'need email')]
    public ?string $email;
}

#[RequestBodyAttribute]
class TestNullableScalarWithLengthInput
{
    #[Length(2, 10)]
    public ?string $name;
}

#[RequestBodyAttribute]
class TestNonNullableScalarInput
{
    public string $email;
}
