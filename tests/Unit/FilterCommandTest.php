<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Switon\Core\ConsoleInterface;
use Switon\Core\FilterMatcherInterface;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\Command\FilterCommand;
use Switon\Http\FilterDiscoveryInterface;
use Switon\Http\RequestHandlerInterface;
use stdClass;

use function json_decode;
use function str_contains;

#[AllowMockObjectsWithoutExpectations]
class FilterCommandTest extends TestCase
{
    protected FilterCommand $command;
    protected ConsoleInterface&MockObject $console;
    protected FilterDiscoveryInterface&MockObject $filterDiscovery;
    protected FilterMatcherInterface&MockObject $filterMatcher;
    protected FakeRequestHandler $requestHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->console = $this->createMock(ConsoleInterface::class);
        $this->filterDiscovery = $this->createMock(FilterDiscoveryInterface::class);
        $this->filterMatcher = $this->createMock(FilterMatcherInterface::class);
        $this->requestHandler = new FakeRequestHandler();

        $this->command = new FilterCommandProbe();
        $this->command->setDependencies(
            $this->console,
            $this->filterDiscovery,
            $this->requestHandler,
            $this->filterMatcher,
        );
    }

    public function testListActionWithEnabledFlagAndNoHandlerFiltersPropertyReturnsEmpty(): void
    {
        $handler = new NoFiltersPropertyRequestHandler();
        $this->command->setRequestHandler($handler);

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([DummyFilterAlpha::class]);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $code = $this->command->listAction('', true);

        $this->assertSame(0, $code);
        $this->assertSame([], $captured);
    }

    public function testListActionOutputsUnionAndIntersectionEventSignatures(): void
    {
        $this->requestHandler->filters = [DummyFilterUnion::class, DummyFilterIx::class];

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([DummyFilterUnion::class, DummyFilterIx::class]);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $this->command->listAction();

        $this->assertCount(2, $captured);
        $union = $captured[0]['class'] === DummyFilterUnion::class ? $captured[0] : $captured[1];
        $ix = $captured[0]['class'] === DummyFilterIx::class ? $captured[0] : $captured[1];
        $this->assertStringContainsString('|', $union['events'][0]);
        $this->assertStringContainsString('&', $ix['events'][0]);
    }

    public function testListActionOutputsNullDescriptionWhenFilterClassHasNoDocblock(): void
    {
        $this->requestHandler->filters = [DummyFilterNoDoc::class];

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([DummyFilterNoDoc::class]);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $this->command->listAction();

        $this->assertNull($captured[0]['description']);
    }

    public function testListActionOutputsNullDescriptionWhenDocblockHasNoSummaryLine(): void
    {
        $this->requestHandler->filters = [DummyFilterEmptyDocblock::class];

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([DummyFilterEmptyDocblock::class]);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $this->command->listAction();

        $this->assertNull($captured[0]['description']);
    }

    public function testListActionOutputsEnabledFiltersAndSkipsMissingClasses(): void
    {
        $this->requestHandler->filters = [
            DummyFilterAlpha::class,
            new DummyFilterBeta(),
        ];

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([
                DummyFilterAlpha::class,
                DummyFilterBeta::class,
                'Switon\\Http\\Tests\\Unit\\MissingFilter',
            ]);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $code = $this->command->listAction();

        $this->assertSame(0, $code);
        $this->assertCount(2, $captured);
        $this->assertSame(DummyFilterAlpha::class, $captured[0]['class']);
        $this->assertSame('Alpha filter description.', $captured[0]['description']);
        $this->assertSame(['stdClass@onAlpha'], $captured[0]['events']);
        $this->assertTrue($captured[0]['enabled']);
        $this->assertSame(DummyFilterBeta::class, $captured[1]['class']);
        $this->assertSame(['*@onAny'], $captured[1]['events']);
        $this->assertTrue($captured[1]['enabled']);
    }

    public function testListActionWithEnabledFlagReturnsOnlyEnabledFilters(): void
    {
        $this->requestHandler->filters = [DummyFilterAlpha::class];

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([DummyFilterAlpha::class, DummyFilterBeta::class]);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $code = $this->command->listAction('', true);

        $this->assertSame(0, $code);
        $this->assertCount(1, $captured);
        $this->assertSame(DummyFilterAlpha::class, $captured[0]['class']);
        $this->assertTrue($captured[0]['enabled']);
    }

    public function testListActionUsesFilterMatcherToFilterPayload(): void
    {
        $this->requestHandler->filters = [DummyFilterAlpha::class, DummyFilterBeta::class];

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([DummyFilterAlpha::class, DummyFilterBeta::class]);

        $this->filterMatcher->expects($this->exactly(2))
            ->method('matchAny')
            ->with('beta', $this->isArray())
            ->willReturnCallback(static function (string $filter, array $subjects): bool {
                return str_contains($subjects['class'], 'Beta');
            });

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $code = $this->command->listAction('beta');

        $this->assertSame(0, $code);
        $this->assertCount(1, $captured);
        $this->assertSame(DummyFilterBeta::class, $captured[0]['class']);
    }

    public function testListActionBuildsEventSignatureForListenerWithNoParameters(): void
    {
        $this->requestHandler->filters = [DummyFilterNoParamListener::class];

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([DummyFilterNoParamListener::class]);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $this->command->listAction();

        $this->assertSame(['onEvt'], $captured[0]['events']);
    }

    public function testListActionEnabledMapIgnoresFiltersPropertyWhenNotArray(): void
    {
        $handler = new RequestHandlerWithNonArrayFilters();
        $this->command->setRequestHandler($handler);

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([DummyFilterAlpha::class]);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $code = $this->command->listAction('', true);

        $this->assertSame(0, $code);
        $this->assertSame([], $captured);
    }

    public function testListActionPassesFilePathToFilterMatcherWhenClassFileIsKnown(): void
    {
        $this->requestHandler->filters = [DummyFilterAlpha::class];

        $this->filterDiscovery->expects($this->once())
            ->method('discover')
            ->willReturn([DummyFilterAlpha::class]);

        $this->filterMatcher->expects($this->once())
            ->method('matchAny')
            ->with(
                'any',
                $this->callback(static function (array $subjects): bool {
                    return isset($subjects['file'])
                        && is_string($subjects['file'])
                        && $subjects['file'] !== '';
                })
            )
            ->willReturn(true);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $this->command->listAction('any');

        $this->assertCount(1, $captured);
    }
}

final class FilterCommandProbe extends FilterCommand
{
    public function setDependencies(
        ConsoleInterface         $console,
        FilterDiscoveryInterface $filterDiscovery,
        RequestHandlerInterface  $requestHandler,
        FilterMatcherInterface   $filterMatcher,
    ): void {
        $this->console = $console;
        $this->filterDiscovery = $filterDiscovery;
        $this->requestHandler = $requestHandler;
        $this->filterMatcher = $filterMatcher;
    }

    public function setRequestHandler(RequestHandlerInterface $requestHandler): void
    {
        $this->requestHandler = $requestHandler;
    }
}

class FakeRequestHandler implements RequestHandlerInterface
{
    /** @var array<int, object|string> */
    public array $filters = [];

    public function boot(): void
    {
    }

    public function handle(): void
    {
    }
}

final class NoFiltersPropertyRequestHandler implements RequestHandlerInterface
{
    public function boot(): void
    {
    }

    public function handle(): void
    {
    }
}

final class RequestHandlerWithNonArrayFilters implements RequestHandlerInterface
{
    /** @var mixed intentionally non-array for {@see FilterCommand::getEnabledFilterMap()} */
    public mixed $filters = 'not-an-array';

    public function boot(): void
    {
    }

    public function handle(): void
    {
    }
}

/**
 * Alpha filter description.
 */
class DummyFilterAlpha
{
    #[EventListener]
    public function onAlpha(stdClass $event): void
    {
    }
}

class DummyFilterBeta
{
    #[EventListener]
    public function onAny(object $event): void
    {
    }
}

interface DummyFilterEvtA
{
}

interface DummyFilterEvtB
{
}

class DummyFilterUnion
{
    #[EventListener]
    public function onUnion(DummyFilterEvtA|DummyFilterEvtB $event): void
    {
    }
}

interface DummyFilterIxA
{
}

interface DummyFilterIxB
{
}

class DummyFilterIx
{
    #[EventListener]
    public function onIx(DummyFilterIxA&DummyFilterIxB $event): void
    {
    }
}

class DummyFilterNoDoc
{
    #[EventListener]
    public function onEvt(stdClass $event): void
    {
    }
}

class DummyFilterEmptyDocblock
{
    #[EventListener]
    public function onEvt(stdClass $event): void
    {
    }
}

class DummyFilterNoParamListener
{
    #[EventListener]
    public function onEvt(): void
    {
    }
}
