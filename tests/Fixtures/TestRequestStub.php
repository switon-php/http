<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Fixtures;

use Switon\Http\Request\FileInterface;
use Switon\Http\RequestContext;
use Switon\Http\RequestInterface;

class TestRequestStub implements RequestInterface
{
    protected ?string $queryParam = null;
    protected ?string $headerValue = null;
    protected array $attributes = [];

    public function setQueryParam(?string $value): void
    {
        $this->queryParam = $value;
    }

    public function setHeaderValue(?string $value): void
    {
        $this->headerValue = $value;
    }

    public function query(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return [];
        }
        return $this->queryParam ?? $default;
    }

    public function parseBody(): void
    {
    }

    public function header(string $name, ?string $default = null): ?string
    {
        if (strtolower($name) === 'accept-language') {
            return $this->headerValue ?? $default;
        }
        return $default;
    }

    public function rawBody(): ?string
    {
        return null;
    }

    public function all(): array
    {
        return [];
    }

    public function only(array $names): array
    {
        return [];
    }

    public function except(array $names): array
    {
        return [];
    }

    public function filters(array $fields = [], array $custom = []): array
    {
        return [];
    }

    public function get(string|int $name, mixed $default = null): mixed
    {
        return $default;
    }

    public function has(string|int $name): bool
    {
        return false;
    }

    public function post(?string $name = null, mixed $default = null): mixed
    {
        return $default;
    }

    public function hasHeader(string $name): bool
    {
        return false;
    }

    public function headers(): array
    {
        return [];
    }

    public function server(?string $name = null, mixed $default = null): mixed
    {
        return $default;
    }

    public function verb(): string
    {
        return 'GET';
    }

    public function isVerb(string $verb): bool
    {
        return $verb === 'GET';
    }

    public function isAjax(): bool
    {
        return false;
    }

    public function scheme(): string
    {
        return 'http';
    }

    public function host(): string
    {
        return 'example.com';
    }

    public function port(): int
    {
        return 80;
    }

    public function isSecure(): bool
    {
        return false;
    }

    public function wantsJson(): bool
    {
        return false;
    }

    public function ip(): string
    {
        return '127.0.0.1';
    }

    public function files(bool $onlySuccessful = true): array
    {
        return [];
    }

    public function file(?string $key = null): ?FileInterface
    {
        return null;
    }

    public function hasFile(string $key): bool
    {
        return false;
    }

    public function route(?string $name = null, mixed $default = null): mixed
    {
        return $default;
    }

    public function handler(): string
    {
        return 'TestController::test';
    }

    public function origin(bool $strict = true): string
    {
        return 'http://example.com';
    }

    public function url(bool $withQuery = false): string
    {
        return 'http://example.com';
    }

    public function elapsed(int $precision = 3): float
    {
        return 0.0;
    }

    public function path(bool $withQuery = false): string
    {
        return '/';
    }

    public function attribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function getContext(): RequestContext
    {
        $context = new RequestContext();
        $context->attributes = $this->attributes;

        return $context;
    }
}
