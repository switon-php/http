<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ClassScannerInterface;

use function sort;

/**
 * Builds and caches the HTTP filter registry.
 *
 * Guidance: Framework package filters belong in composer.json `extra.switon.filters`; app filters under `@app/Filter` and `@app/Areas/{area}/Filter` are scanned automatically.
 *
 * Road-signs:
 * - packages: {@see ComposerExtraInterface::collect()} <code>switon.filters</code>
 * - app: {@see ClassScannerInterface} + <code>$files</code>
 * - returns candidate FQCNs only
 *
 * @see \Switon\Http\FilterDiscoveryInterface
 * @see \Switon\Core\ClassScannerInterface
 * @see \Switon\ComposerExtra\ComposerExtraInterface
 * @see \Switon\Http\RequestHandler Runtime enable point
 */
class FilterDiscovery implements FilterDiscoveryInterface
{
    #[Autowired] protected ClassScannerInterface $classScanner;
    #[Autowired] protected ComposerExtraInterface $composerExtra;

    /**
     * Glob entries for application filter classes (path → FQCN template via ClassScanner).
     *
     * @var array<string, string>
     */
    #[Autowired] protected array $files
        = [
            '@app/Filter/*Filter.php' => 'App\\Filter\\*Filter',
            '@app/Areas/*/Filter/*Filter.php' => 'App\\Areas\\*\\Filter\\*Filter',
        ];

    /** @var list<string> Cached filter class list. */
    protected array $filters = [];

    /**
     * @return list<string>
     * {@inheritDoc}
     */
    public function discover(): array
    {
        if ($this->filters === []) {
            /** @var list<string> $filters */
            $filters = [];

            foreach ($this->classScanner->scan($this->files) as $className) {
                $filters[] = $className;
            }

            foreach ($this->composerExtra->collect('switon.filters') as $class) {
                $filters[] = $class;
            }

            sort($filters);

            $this->filters = $filters;
        }

        return $this->filters;
    }
}
