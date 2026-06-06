<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\InputInterface;
use Switon\Http\Exception\BadRequestException;
use Switon\Http\Request\FileInterface;

/**
 * HTTP request read boundary.
 *
 * Guidance: Use CookiesInterface for cookie operations; RequestInterface is for input, metadata, and uploads.
 *
 * Road-signs:
 * - merged input: get / all / only / except
 * - null semantics: get / has treat null like missing input
 * - route + query: route / query / path
 * - raw or parsed body: rawBody / parseBody
 * - uploads: files / file / hasFile
 * - negotiation: wantsJson
 *
 * @see \Switon\Http\Request
 * @see \Switon\Core\InputInterface
 * @see \Switon\Http\CookiesInterface
 * @see \Switon\Http\ResponseInterface
 * @see \Switon\Http\RequestHandler
 * @see \Switon\Http\RequestContext
 * @see \Switon\Http\Request\FileInterface
 */
interface RequestInterface extends InputInterface
{
    /**
     * Return request context.
     */
    public function getContext(): RequestContext;

    /**
     * Return the transport raw body before parsing.
     */
    public function rawBody(): ?string;

    /**
     * Parse raw body into POST and merged input.
     *
     * Runs after {@see \Switon\Http\Event\RequestAdjust} in {@see \Switon\Http\RequestHandler::handle()}.
     *
     * @throws BadRequestException When Content-Type is JSON and the body is not valid JSON
     */
    public function parseBody(): void;

    /**
     * Return merged input payload.
     *
     * Merge priority: query -> POST/json -> route.
     *
     * @return array<string, mixed>
     *
     * @see \Switon\Http\RequestHandler::handle()
     * @see \Switon\Binding\ArgumentsBinder::resolve()
     * @see \Switon\Binding\ScalarResolver
     * @see \Switon\Http\Attribute\RequestData
     * @see \Switon\Http\Attribute\RequestBody
     * @see \Switon\Core\InputInterface::all()
     */
    public function all(): array;

    /**
     * Return only the named merged input fields.
     *
     * @param list<string|int> $names
     *
     * @return array<string, mixed>
     */
    public function only(array $names): array;

    /**
     * Return merged input without the named fields.
     *
     * @param list<string|int> $names
     *
     * @return array<string, mixed>
     */
    public function except(array $names): array;

    /**
     * Extract merged input as query filters.
     *
     * `$fields` must be indexed and may carry operator suffixes such as `*=`, `@=`, `>=`, `?=`.
     * Empty strings and null values are filtered out. Operators are preserved in the returned keys.
     * Relation fields like `author.name*=` match the base field and keep the original filter key.
     *
     * @param list<string> $fields
     * @param array<string, mixed> $custom
     *
     * @return array<string, mixed>
     *
     * @see \Switon\Orm\RepositoryInterface Filter format (operators contract)
     * @see \Switon\Http\Request::filters() Default implementation
     * @see \Switon\Query\AbstractConditionBuilder::where() Filter-key operator parser (>=, *=, ~=, ?=, ...)
     * @see \Switon\Orm\AbstractRepository::where() ORM pre-processing (e.g. field@= → field~= / >= / <=)
     */
    public function filters(array $fields = [], array $custom = []): array;

    /**
     * Read one merged input value.
     *
     * Route wins over POST, POST wins over query.
     * Missing key or explicit `null` both return `$default`.
     *
     * @param string|int $name The input field name
     * @param mixed $default The default value to return if the input doesn't exist
     *
     * @return mixed The input value or the default value
     *
     * @see route() For getting route parameters only
     */
    public function get(string|int $name, mixed $default = null): mixed;

    /**
     * Check merged input presence.
     *
     * Returns true only when the merged key resolves to a non-null value.
     *
     * @param string|int $name The input field name to check
     *
     * @return bool True if the field exists, false otherwise
     */
    public function has(string|int $name): bool;

    /**
     * Whether the client expects a JSON response body (not an HTML view).
     *
     * True when the {@literal Accept} header indicates JSON (e.g. contains {@literal application/json}),
     * or when {@see isAjax()} (typical XHR: {@literal X-Requested-With: XMLHttpRequest}).
     *
     * @see \Switon\Http\RequestHandler::shouldInvokeAction()
     */
    public function wantsJson(): bool;

    /**
     * Read one query-string value or the full query map.
     *
     * Pass null to get all query data.
     */
    public function query(?string $name = null, mixed $default = null): mixed;

    /**
     * Read POST/body data only.
     *
     * Pass null to get the full parsed POST payload.
     */
    public function post(?string $name = null, mixed $default = null): mixed;

    /**
     * Read one HTTP header.
     *
     * Header names are case-insensitive.
     */
    public function header(string $name, ?string $default = null): ?string;

    /**
     * Check HTTP header presence.
     */
    public function hasHeader(string $name): bool;

    /**
     * Return normalized request headers.
     *
     * @return array<string, string>
     */
    public function headers(): array;

    /**
     * Read one server variable or the full server map.
     */
    public function server(?string $name = null, mixed $default = null): mixed;

    /**
     * Return the HTTP verb in uppercase.
     */
    public function verb(): string;

    /**
     * Check whether the current verb matches.
     */
    public function isVerb(string $verb): bool;

    /**
     * Check `X-Requested-With: XMLHttpRequest`.
     */
    public function isAjax(): bool;

    /**
     * Return the URI scheme, usually `http` or `https`.
     */
    public function scheme(): string;

    /**
     * Return host header or server host fallback.
     */
    public function host(): string;

    /**
     * Return the request port.
     */
    public function port(): int;

    /**
     * Check whether the request is HTTPS.
     */
    public function isSecure(): bool;

    /**
     * Return the client IP address.
     *
     * Implementations may prefer proxy headers such as `X-Real-IP`.
     *
     * @see \Switon\Http\Server\Network For IP address utilities
     */
    public function ip(): string;

    /**
     * Return uploaded files.
     *
     * Default behavior keeps only successful uploads.
     *
     * @return list<FileInterface>
     *
     * @see \Switon\Http\Request\FileInterface For file handling methods
     * @see file() For getting a single file by key
     */
    public function files(bool $onlySuccessful = true): array;

    /**
     * Return one uploaded file by form key.
     *
     * Pass null to get the first available file.
     */
    public function file(?string $key = null): ?FileInterface;

    /**
     * Check successful uploaded-file presence.
     */
    public function hasFile(string $key): bool;

    /**
     * Return the Origin header.
     *
     * When `$strict` is false, implementations may fall back to Referer.
     */
    public function origin(bool $strict = true): string;

    /**
     * Return the full absolute request URL.
     */
    public function url(bool $withQuery = false): string;

    /**
     * Return elapsed seconds since request start.
     */
    public function elapsed(int $precision = 3): float;

    /**
     * Return the normalized request path.
     */
    public function path(bool $withQuery = false): string;

    /**
     * Return the matched route handler string, or empty string before routing.
     */
    public function handler(): string;

    /**
     * Read route parameters only.
     *
     * Pass null to get the full route-variable map.
     */
    public function route(?string $name = null, mixed $default = null): mixed;

    /**
     * Read one custom request attribute.
     */
    public function attribute(string $name): mixed;

    /**
     * Check custom attribute presence.
     *
     * Attribute presence is independent from the merged input map.
     */
    public function hasAttribute(string $name): bool;

    /**
     * Return all custom request attributes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array;

    /**
     * Store one custom request attribute.
     */
    public function setAttribute(string $name, mixed $value): void;
}
