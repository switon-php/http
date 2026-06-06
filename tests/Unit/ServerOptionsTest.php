<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Http\ServerOptions;
use Switon\Http\Tests\TestCase;

class ServerOptionsTest extends TestCase
{
    public function testServerOptionsHasDefaultValues(): void
    {
        $options = $this->container->make(ServerOptions::class);

        $this->assertSame('0.0.0.0', $options->host);
        $this->assertSame(9501, $options->port);
        $this->assertSame('auto', $options->type);
        $this->assertIsArray($options->settings);
    }

    public function testServerOptionsCanBeInstantiatedWithCustomValues(): void
    {
        $options = $this->container->make(ServerOptions::class, [
            'host' => '127.0.0.1',
            'port' => 8080,
            'type' => 'swoole',
            'settings' => ['worker_num' => 4]
        ]);

        $this->assertSame('127.0.0.1', $options->host);
        $this->assertSame(8080, $options->port);
        $this->assertSame('swoole', $options->type);
        $this->assertSame(['worker_num' => 4], $options->settings);
    }
}
