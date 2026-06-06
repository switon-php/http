<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Fixtures;

use Switon\Http\Controller;
use Switon\Routing\Attribute\GetMapping;
use Switon\Routing\Attribute\RequestMapping;

/**
 * Simple controller for integration tests (single-route request handling).
 */
#[RequestMapping('')]
class MiniAppStyleController extends Controller
{
    #[GetMapping('/hello')]
    public function helloAction(): array
    {
        return ['message' => 'Hello!'];
    }

    #[GetMapping('/greet/{name}')]
    public function greetAction(string $name): array
    {
        return ['message' => "Hello, {$name}!"];
    }
}
