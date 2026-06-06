<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\ContextConnScoped;
use Switon\Routing\MatcherInterface;

/**
 * Stores per-request HTTP request state.
 *
 * Implements ContextConnScoped to persist across multiple messages
 * within a WebSocket connection.
 *
 * @see \Switon\Http\Request
 * @see \Switon\Http\RequestInterface
 */
class RequestContext implements ContextConnScoped
{
    /** @var array<string, mixed> */
    public array $_GET = [];
    /** @var array<string, mixed> */
    public array $_POST = [];
    /** @var array<string, mixed> */
    public array $_REQUEST = [];
    /** @var array<string, mixed> */
    public array $_SERVER = [];
    /** @var array<string, mixed> */
    public array $_FILES = [];
    /** @var string|null */
    public ?string $rawBody = null;
    /** @var array<string, mixed> */
    public array $headers = [];
    /** @var array<string, mixed> */
    public array $attributes = [];
    public ?MatcherInterface $matcher = null;
}
