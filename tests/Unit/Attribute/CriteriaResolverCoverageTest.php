<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Attribute;

use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Switon\Core\Exception\RuntimeException;
use Switon\Core\MakerInterface;
use Switon\Http\Attribute\Fields;
use Switon\Http\Attribute\IdentityFilter;
use Switon\Http\Attribute\Filters;
use Switon\Http\Attribute\Keyword;
use Switon\Http\Attribute\Orders;
use Switon\Http\Attribute\SoftDeleteFilter;
use Switon\Http\Attribute\TenantFilter;
use Switon\Http\Criteria;
use Switon\Http\CriteriaResolver;
use Switon\Http\KeywordTransformerInterface;
use Switon\Http\Request\File\Exception as FileException;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use Switon\Principal\IdentityInterface;
use Switon\Principal\TenantInterface;
use Switon\Routing\Attribute\GetMapping;

final class CriteriaResolverCoverageTest extends TestCase
{
    public function testResolveRejectsUnexpectedType(): void
    {
        $resolver = new CriteriaResolver();
        $parameter = $this->criteriaParameter();

        $this->expectException(RuntimeException::class);

        $resolver->resolve($parameter, 'array');
    }

    public function testResolveMergesIdentityFilterScopeIntoCriteria(): void
    {
        $resolver = new CriteriaResolver();
        $identity = $this->createStub(IdentityInterface::class);
        $identity->method('getId')->willReturn(88);
        $this->container->replace(IdentityInterface::class, $identity);
        $this->inject($resolver, 'maker', $this->container->get(MakerInterface::class));

        $controller = new class () {
            #[IdentityFilter('owner_id')]
            public function indexAction(Criteria $criteria): void
            {
            }
        };

        $parameter = (new ReflectionMethod($controller, 'indexAction'))->getParameters()[0];
        $criteria = $resolver->resolve($parameter, Criteria::class);

        $this->assertSame(['owner_id' => 88], $criteria->filters);
    }

    public function testResolveAppliesCriteriaAttributesAndSkipsNonCriteriaAttributes(): void
    {
        $resolver = new CriteriaResolver();
        $maker = $this->createMock(MakerInterface::class);
        $maker->expects($this->exactly(3))
            ->method('make')
            ->willReturnCallback(fn (string $name, array $arguments) => $this->container->make($name, $arguments));
        $this->inject($resolver, 'maker', $maker);

        $controller = new class () {
            #[Fields(['id', 'email'])]
            #[Orders(['created_at' => -1])]
            #[SoftDeleteFilter('deleted_at')]
            #[GetMapping('/items')]
            public function indexAction(Criteria $criteria): void
            {
            }
        };

        $parameter = (new ReflectionMethod($controller, 'indexAction'))->getParameters()[0];
        $criteria = $resolver->resolve($parameter, Criteria::class);

        $this->assertSame(['id', 'email'], $criteria->fields);
        $this->assertSame(['created_at' => -1], $criteria->orders);
        $this->assertSame(['deleted_at' => 0], $criteria->filters);
    }

    public function testFiltersMergesRequestFiltersAheadOfExistingCriteria(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('filters')
            ->with(['status', 'owner_id'])
            ->willReturn(['status' => 'published', 'owner_id' => 7]);
        $this->container->replace(RequestInterface::class, $request);

        $attribute = new Filters(['status', 'owner_id']);
        $this->injector->inject($attribute);

        $criteria = new Criteria();
        $criteria->filters = ['status' => 'draft', 'existing' => 'keep'];

        $attribute->apply($criteria, $this->criteriaParameter());

        $this->assertSame(
            ['status' => 'draft', 'owner_id' => 7, 'existing' => 'keep'],
            $criteria->filters
        );
    }

    public function testKeywordAppliesTransformedFilterAndPreservesExistingValue(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('get')
            ->with('keyword', '')
            ->willReturn('  acme  ');
        $this->container->replace(RequestInterface::class, $request);

        $transformer = $this->createMock(KeywordTransformerInterface::class);
        $transformer->expects($this->once())
            ->method('transform')
            ->with(['name', 'email'], 'acme', '*=')
            ->willReturn('email*=');
        $this->container->replace(KeywordTransformerInterface::class, $transformer);

        $attribute = new Keyword(['name', 'email']);
        $this->injector->inject($attribute);

        $criteria = new Criteria();
        $criteria->filters = ['email*=' => 'existing'];

        $attribute->apply($criteria, $this->criteriaParameter());

        $this->assertSame(['email*=' => 'existing'], $criteria->filters);
    }

    public function testKeywordSkipsBlankKeywordAndEmptyFields(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('get')
            ->with('keyword', '')
            ->willReturn('   ');
        $this->container->replace(RequestInterface::class, $request);

        $transformer = $this->createMock(KeywordTransformerInterface::class);
        $transformer->expects($this->never())
            ->method('transform');
        $this->container->replace(KeywordTransformerInterface::class, $transformer);

        $attribute = new Keyword([]);
        $this->injector->inject($attribute);

        $criteria = new Criteria();
        $attribute->apply($criteria, $this->criteriaParameter());

        $this->assertSame([], $criteria->filters);
    }

    public function testTenantFilterAppliesTenantId(): void
    {
        $tenant = $this->createStub(TenantInterface::class);
        $tenant->method('getId')->willReturn('tenant-17');
        $this->container->replace(TenantInterface::class, $tenant);

        $attribute = new TenantFilter('tenant_id');
        $this->injector->inject($attribute);

        $criteria = new Criteria();
        $attribute->apply($criteria, $this->criteriaParameter());

        $this->assertSame(['tenant_id' => 'tenant-17'], $criteria->filters);
    }

    public function testRequestFileExceptionReturnsBadRequestStatusCode(): void
    {
        $this->assertSame(400, FileException::of('broken')->getStatusCode());
    }

    private function criteriaParameter(): ReflectionParameter
    {
        return (new ReflectionMethod($this, 'dummyCriteriaAction'))->getParameters()[0];
    }

    private function dummyCriteriaAction(Criteria $criteria): void
    {
    }

    private function inject(object $object, string $property, mixed $value): void
    {
        $reflectionProperty = new ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }
}
