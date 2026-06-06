<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Attribute;

use Switon\Http\Attribute\IdentityFilter;
use Switon\Principal\IdentityInterface;
use Switon\Http\Tests\TestCase;

class IdentityFilterTest extends TestCase
{
    public function testToFiltersReturnsCurrentIdentityIdForDefaultSubject(): void
    {
        $identity = $this->createStub(IdentityInterface::class);
        $identity->method('getId')->willReturn(99);
        $this->container->replace(IdentityInterface::class, $identity);

        $filter = new IdentityFilter('owner_id');
        $this->injector->inject($filter);

        $this->assertSame(['owner_id' => 99], $filter->toFilters());
    }

    public function testToFiltersUsesNameWhenSubjectIsName(): void
    {
        $identity = $this->createStub(IdentityInterface::class);
        $identity->method('getName')->willReturn('alice');
        $this->container->replace(IdentityInterface::class, $identity);

        $filter = new IdentityFilter('username', 'name');
        $this->injector->inject($filter);

        $this->assertSame(['username' => 'alice'], $filter->toFilters());
    }

    public function testToFiltersReturnsNullWhenFieldIsEmpty(): void
    {
        $identity = $this->createStub(IdentityInterface::class);
        $identity->method('getId')->willReturn(1);
        $this->container->replace(IdentityInterface::class, $identity);

        $filter = new IdentityFilter('');
        $this->injector->inject($filter);

        $this->assertNull($filter->toFilters());
    }
}
