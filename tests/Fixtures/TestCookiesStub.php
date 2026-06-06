<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Fixtures;

use Switon\Http\CookiesInterface;

class TestCookiesStub implements CookiesInterface
{
    protected array $cookies = [];

    public function setCookie(string $name, string $value): void
    {
        $this->cookies[$name] = $value;
    }

    public function all(): array
    {
        return $this->cookies;
    }

    public function set(
        string  $name,
        string  $value,
        int     $expire = 0,
        ?string $path = null,
        ?string $domain = null,
        bool    $secure = false,
        bool    $httponly = true
    ): static {
        $this->cookies[$name] = $value;
        return $this;
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->cookies[$name]);
    }

    public function delete(string $name, ?string $path = null, ?string $domain = null): static
    {
        unset($this->cookies[$name]);
        return $this;
    }
}
