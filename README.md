# Switon HTTP Package

[![HTTP CI](https://img.shields.io/github/actions/workflow/status/switon-php/http/ci.yml?branch=main&label=HTTP%20CI)](https://github.com/switon-php/http/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's HTTP pipeline for request and response handling, attribute routing, action return normalization, request-stage
filters, and transport adapters.

## Highlights

- **Request pipeline:** `RequestHandlerInterface` drives adjust, auth, route, validate, invoke, render, and finish
  stages.
- **Attribute routes:** `#[RequestMapping]` and action mappings keep routes close to controllers.
- **Action input binding:** `RequestBody`, `RequestData`, `RequestQuery`, and `Filters` shape action input.
- **Request filters:** cross-cutting filters can add request IDs, locale, CORS, and slowlog handling.
- **Return normalization:** `NormalizeActionReturnTransformer` turns common return values into framework responses.
- **Response helpers:** `ResponseInterface` covers `json()`, `text()`, `raw()`, and `redirect()`.
- **Runtime modes:** `ServerOptions` can switch between `auto`, `fpm`, `php`, and `swoole`.

## Installation

```bash
composer require switon/http
```

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Http\Controller;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Routing\Attribute\GetMapping;
use Switon\Routing\Attribute\RequestMapping;

#[RequestMapping('/api/users')]
final class UserController extends Controller
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;

    #[GetMapping('{id}')]
    public function showAction(int $id): array
    {
        return [
            'id' => $id,
            'locale' => $this->request->query('locale'),
        ];
    }

    #[GetMapping('{id}/raw')]
    public function rawAction(int $id): ResponseInterface
    {
        return $this->response->json(['id' => $id]);
    }
}
```

Docs: https://docs.switon.dev/latest/http

## License

MIT.
