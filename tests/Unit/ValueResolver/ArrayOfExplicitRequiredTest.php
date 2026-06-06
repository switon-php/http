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
use Switon\Validating\Attribute\ArrayOf;
use Switon\Validating\Attribute\Required;
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;

/**
 * Test explicit #[Required] attribute on array fields.
 *
 * @group http
 * @group array-of-input
 */
#[AllowMockObjectsWithoutExpectations]
class ArrayOfExplicitRequiredTest extends TestCase
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

    /**
     * Test that explicit #[Required] on array field works.
     */
    public function testExplicitRequiredOnArrayField(): void
    {
        // Arrange - array field with explicit #[Required] is missing
        $inputData = [
            'title' => 'Test',
            // 'items' is missing and has explicit #[Required]
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(TestRequestWithExplicitRequired::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert - should have the custom Required error message
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('items', $errors);
        $this->assertStringContainsString('You must provide items', $errors['items']);
    }

    protected function createMockParameter(string $typeName, string $paramName): ReflectionParameter
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

// Test fixture

#[RequestBodyAttribute]
class TestRequestWithExplicitRequired
{
    public string $title;

    // Explicit #[Required] with custom message
    #[ArrayOf('string')]
    #[Required(message: 'You must provide items')]
    public array $items;
}
