<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Switon\Core\Exception\InvalidArgumentException;
use Switon\Http\Exception\InvalidUrlPathException;

class InvalidUrlPathExceptionTest extends TestCase
{
    public function testInvalidUrlPathExceptionInheritsFromInvalidArgumentException(): void
    {
        $exception = InvalidUrlPathException::of('Invalid path.');

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
    }
}
