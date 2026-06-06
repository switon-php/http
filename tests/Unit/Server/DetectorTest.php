<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
use Switon\Http\Server\Detector;

final class DetectorTest extends TestCase
{
    public function testDetectReturnsPhpFamilyUnderCli(): void
    {
        if (PHP_SAPI !== 'cli') {
            $this->markTestSkipped('PHPUnit runs under cli in this project');
        }

        $type = Detector::detect();
        $this->assertContains($type, ['#php', '#swoole']);
    }
}
