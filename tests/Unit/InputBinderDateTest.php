<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Binding\InputBinder;
use Switon\Core\Filesystem;
use Switon\Core\FilesystemInterface;
use Switon\Core\LocaleInterface;
use Switon\Core\PathAliasInterface;
use Switon\Testing\Container;
use Switon\Validating\Attribute\Date;
use Switon\Validating\Validator;
use Switon\Validating\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
class InputBinderDateTest extends TestCase
{
    public function testDateConvertsStringIntoTimestampBeforeTypeValidation(): void
    {
        $binder = $this->createBinder();

        $result = $binder->bind(TestDateIntInput::class, ['date' => '2023-12-25']);

        $this->assertIsInt($result->date);
        $this->assertSame(strtotime('2023-12-25'), $result->date);
    }

    public function testDateFormatsTimestampIntoStringBeforeTypeValidation(): void
    {
        $binder = $this->createBinder();
        $timestamp = strtotime('2023-12-25 10:30:00');

        $result = $binder->bind(TestDateStringInput::class, ['date' => $timestamp]);

        $this->assertSame('2023-12-25', $result->date);
    }

    protected function createValidator(): ValidatorInterface
    {
        $container = new Container();
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('get')->willReturn('en');
        $locale->method('set')->willReturnSelf();

        $filesystem = $this->createMock(Filesystem::class);
        $templateDir = $container->get(PathAliasInterface::class)->get('@switon.validator.resources');
        $this->assertIsString($templateDir);
        $filesystem->method('glob')->willReturn([
            $templateDir . '/en.php',
            $templateDir . '/zh-cn.php',
        ]);

        $container->replace(LocaleInterface::class, $locale);
        $container->replace(FilesystemInterface::class, $filesystem);

        return $container->make(Validator::class, [
            'dirs' => [$templateDir],
        ]);
    }

    protected function createBinder(): InputBinder
    {
        $container = new Container();
        return $container->make(InputBinder::class, ['validator' => $this->createValidator()]);
    }
}

class TestDateIntInput
{
    #[Date]
    public int $date;
}

class TestDateStringInput
{
    #[Date('Y-m-d')]
    public string $date;
}
