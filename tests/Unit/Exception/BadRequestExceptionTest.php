<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Exception;

use Switon\Http\Exception\BadRequestException;
use Switon\Http\Tests\TestCase;

class BadRequestExceptionTest extends TestCase
{
    public function testGetStatusCodeReturns400(): void
    {
        $e = BadRequestException::of('Bad');

        $this->assertSame(400, $e->getStatusCode());
    }
}
