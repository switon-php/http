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
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;

/**
 * Test error reporting when array fields are missing.
 *
 * @group http
 * @group array-of-input
 */
#[AllowMockObjectsWithoutExpectations]
class ArrayOfMissingFieldTest extends TestCase
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
     * Test that missing required array field (no default, non-nullable) generates error.
     */
    public function testMissingRequiredArrayFieldWithoutMinItems(): void
    {
        // Arrange - items field is completely missing
        $inputData = [
            'title' => 'Test',
            // 'requiredArray' is missing
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(TestRequestWithRequiredArray::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert - should have Required error
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('requiredArray', $errors);
        $this->assertStringContainsString('required', strtolower($errors['requiredArray']));
    }

    /**
     * Test that missing array with minItems constraint generates Required error.
     *
     * Note: When the field is completely missing, Required error takes precedence
     * over minItems constraint. The minItems constraint is only checked when the
     * array is present but empty.
     */
    public function testMissingArrayFieldWithMinItems(): void
    {
        // Arrange
        $inputData = [
            'title' => 'Test',
            // 'items' is missing, but has minItems: 1
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(TestRequestWithMinItems::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert - should have Required error (field is missing entirely)
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('items', $errors);
        $this->assertStringContainsString('required', strtolower($errors['items']));
    }

    /**
     * Test that empty array violates minItems constraint.
     */
    public function testEmptyArrayViolatesMinItems(): void
    {
        // Arrange - array is present but empty
        $inputData = [
            'title' => 'Test',
            'items' => [],  // Empty array violates minItems: 1
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(TestRequestWithMinItems::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert - should have minItems error
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('items', $errors);
        $this->assertStringContainsString('at least', $errors['items']);
    }

    /**
     * Test that empty array is allowed when default value is [].
     */
    public function testEmptyArrayAllowedWithDefaultEmptyArray(): void
    {
        // Arrange - has minItems but also has default value []
        $inputData = [
            'title' => 'Test',
            'items' => [],  // Empty array, but field has default value []
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(TestRequestWithDefaultEmptyArray::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert - no errors, empty array is allowed due to default value
        $errors = $validation->getErrors();
        $this->assertEmpty($errors);
        $this->assertIsArray($result->items);
        $this->assertEmpty($result->items);
    }

    /**
     * Test that optional array field (with default) doesn't generate error when missing.
     */
    public function testMissingOptionalArrayFieldWithDefault(): void
    {
        // Arrange
        $inputData = [
            'title' => 'Test',
            // 'tags' is missing but has default value
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(TestRequestWithOptionalArray::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert - no errors, uses default
        $errors = $validation->getErrors();
        $this->assertEmpty($errors);
        $this->assertIsArray($result->tags);
        $this->assertEmpty($result->tags);  // Uses default empty array
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

// Test fixtures

#[RequestBodyAttribute]
class TestRequestWithRequiredArray
{
    public string $title;

    // Required array (no default, non-nullable)
    #[ArrayOf('string')]
    public array $requiredArray;
}

#[RequestBodyAttribute]
class TestRequestWithMinItems
{
    public string $title;

    // Has minItems constraint
    #[ArrayOf('string', minItems: 1)]
    public array $items;
}

#[RequestBodyAttribute]
class TestRequestWithOptionalArray
{
    public string $title;

    // Optional with default
    #[ArrayOf('string')]
    public array $tags = [];
}

#[RequestBodyAttribute]
class TestRequestWithDefaultEmptyArray
{
    public string $title;

    // Has minItems constraint BUT also has default value []
    // This means empty array should be allowed (use default)
    #[ArrayOf('string', minItems: 1)]
    public array $items = [];
}
