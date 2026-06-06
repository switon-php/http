<?php

declare(strict_types=1);

namespace Switon\Http;

use JsonSerializable;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ContextAware;
use Switon\Core\ContextManagerInterface;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\Event\RequestReceived;

/**
 * Cookie store bound to the current request and response.
 *
 * Road-signs:
 * - RequestReceived seeds request cookies into CookiesContext
 * - get / has / all read current cookies
 * - set / delete queue response cookies through ResponseInterface
 * - CookiesInterface is the caller boundary
 *
 * @see \Switon\Http\CookiesInterface
 * @see \Switon\Http\CookiesContext
 * @see \Switon\Http\Event\RequestReceived
 * @see \Switon\Http\ResponseInterface
 */
class Cookies implements CookiesInterface, ContextAware, JsonSerializable
{
    #[Autowired] protected ContextManagerInterface $contextManager;
    #[Autowired] protected ResponseInterface $response;

    public function getContext(): CookiesContext
    {
        return $this->contextManager->getContext($this);
    }

    #[EventListener] public function onRequestReceived(RequestReceived $event): void
    {
        $this->getContext()->cookies = $event->COOKIE;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->getContext()->cookies;
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
        $this->getContext()->cookies[$name] = $value;
        $this->response->setCookie($name, $value, $expire, $path, $domain, $secure, $httponly);

        return $this;
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->all()[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function delete(string $name, ?string $path = null, ?string $domain = null): static
    {
        unset($this->getContext()->cookies[$name]);

        $this->response->setCookie(
            $name,
            'deleted',
            -1,
            $path ?? '/',
            $domain ?? ''
        );

        return $this;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->all();
    }
}
