<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Http\FilterDiscoveryInterface;
use Switon\Http\Tests\TestCase;

class FilterDiscoveryTest extends TestCase
{
    #[Autowired] protected FilterDiscoveryInterface $discovery;

    #[Autowired] protected ComposerExtraInterface $composerExtra;

    public function testGetFiltersReturnsListOfClassNames(): void
    {
        $filters = $this->discovery->discover();

        $this->assertIsArray($filters);
        foreach ($filters as $filter) {
            $this->assertIsString($filter);
            $this->assertNotSame('', $filter);
        }
    }

    public function testDiscoverReturnsSameCachedListOnSecondCall(): void
    {
        $first = $this->discovery->discover();
        $second = $this->discovery->discover();

        $this->assertSame($first, $second);
    }
}
