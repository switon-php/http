<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\ClassScannerInterface;
use Switon\Http\FilterDiscovery;

/**
 * {@see FilterDiscovery::discover()} merge + {@see sort()} without the shared test container.
 */
final class FilterDiscoveryMergeSortTest extends TestCase
{
    public function testDiscoverMergesScannerAndComposerClassesThenSortsAndCaches(): void
    {
        $scanner = $this->createMock(ClassScannerInterface::class);
        $scanner->expects($this->once())
            ->method('scan')
            ->with($this->identicalTo([]))
            ->willReturn([
                'Zebra\\Filter',
                'Alpha\\Filter',
            ]);

        $composer = $this->createMock(ComposerExtraInterface::class);
        $composer->expects($this->once())
            ->method('collect')
            ->with('switon.filters')
            ->willReturn([
                'Mid\\Filter',
            ]);

        $discovery = new FilterDiscoveryProbe();
        $discovery->setDependencies($scanner, $composer, []);

        $first = $discovery->discover();
        $this->assertSame([
            'Alpha\\Filter',
            'Mid\\Filter',
            'Zebra\\Filter',
        ], $first);

        $this->assertSame($first, $discovery->discover());
    }
}

final class FilterDiscoveryProbe extends FilterDiscovery
{
    /**
     * @param array<string, string> $files
     */
    public function setDependencies(
        ClassScannerInterface  $scanner,
        ComposerExtraInterface $composer,
        array                  $files,
    ): void {
        $this->classScanner = $scanner;
        $this->composerExtra = $composer;
        $this->files = $files;
    }
}
