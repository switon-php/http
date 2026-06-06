<?php

declare(strict_types=1);

namespace Switon\Http;

use JsonSerializable;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ClockInterface;
use Switon\Core\ContextAware;
use Switon\Core\ContextManagerInterface;
use Switon\Core\Exception\JsonException;
use Switon\Core\Json;
use Switon\Core\MakerInterface;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Exception\BadRequestException;
use Switon\Http\Exception\InvalidAssociativeArrayException;
use Switon\Http\Exception\InvalidIndexedArrayException;
use Switon\Http\Request\File;
use Switon\Http\Request\FileInterface;

use function array_is_list;
use function array_key_exists;
use function array_merge;
use function count;
use function current;
use function in_array;
use function is_array;
use function is_int;
use function parse_str;
use function round;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;

/**
 * HTTP request state reader backed by RequestContext.
 *
 * Use this as the mutable request facade during request handling and filter discovery.
 * Road-signs:
 * - RequestReceived seeds _GET / _POST / _REQUEST / headers
 * - parseBody refreshes POST + merged REQUEST after RequestAdjust
 * - get / has use merged REQUEST and treat null like missing
 * - route reads matcher variables attached by RequestHandler
 * - wantsJson folds Accept + XHR + ajax flag
 *
 * @see \Switon\Http\RequestInterface
 * @see \Switon\Http\RequestContext
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\Event\RequestReceived
 * @see \Switon\Http\Event\RequestAdjust
 */
class Request implements RequestInterface, JsonSerializable, ContextAware
{
    #[Autowired] protected ContextManagerInterface $contextManager;
    #[Autowired] protected ClockInterface $clock;
    #[Autowired] protected MakerInterface $maker;

    /**
     * Return the request context for the current request scope.
     */
    public function getContext(): RequestContext
    {
        return $this->contextManager->getContext($this);
    }

    /**
     * Store transport-level globals from {@see RequestReceived}; does not parse JSON or raw body into POST.
     *
     * Body parsing runs later in {@see parseBody()} (after {@see \Switon\Http\Event\RequestAdjust}).
     */
    #[EventListener] public function onRequestReceived(RequestReceived $event): void
    {
        $context = $this->getContext();

        $context->_GET = $event->GET;
        $context->_POST = $event->POST;
        $context->_REQUEST = array_merge($event->GET, $event->POST);
        $context->_SERVER = $event->SERVER;
        $context->rawBody = $event->RAW_BODY;
        $context->_FILES = $event->FILES;

        // Parse headers
        $headers = [];
        foreach ($event->SERVER as $key => $val) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $val;
            }
        }

        if (isset($event->SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $event->SERVER['CONTENT_TYPE'];
        }

        if (isset($event->SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $event->SERVER['CONTENT_LENGTH'];
        }

        $context->headers = $headers;
    }

    /**
     * Parse JSON or urlencoded raw body into {@see RequestContext::$_POST} and refresh {@see RequestContext::$_REQUEST}.
     *
     * Idempotent when {@see RequestContext::$_POST} is already non-empty (e.g. PHP filled `$_POST` for forms) or when
     * the method is GET/OPTIONS, or raw body is null/empty. JSON decode must yield an array for POST; scalar JSON roots
     * become an empty POST array — use {@see rawBody()} for scalar JSON payloads.
     *
     * @throws BadRequestException When Content-Type is JSON and the body is not valid JSON
     *
     * @see \Switon\Http\Event\RequestAdjust
     * @see \Switon\Http\RequestHandler::handle()
     */
    public function parseBody(): void
    {
        $context = $this->getContext();
        $POST = $context->_POST;

        if ($POST !== []) {
            return;
        }

        if (!isset($context->_SERVER['REQUEST_METHOD'])) {
            return;
        }

        $method = $context->_SERVER['REQUEST_METHOD'];
        if (in_array($method, ['GET', 'OPTIONS'], true)) {
            return;
        }

        $rawBody = $context->rawBody;
        if ($rawBody === null || $rawBody === '') {
            return;
        }

        if (isset($context->_SERVER['CONTENT_TYPE']) && $this->isJsonContentType($context->_SERVER['CONTENT_TYPE'])) {
            try {
                $POST = Json::parse($rawBody);
            } catch (JsonException $e) {
                BadRequestException::raise('Invalid JSON body.', [], 0, $e);
            }
            if (!is_array($POST)) {
                $POST = [];
            }
        } else {
            parse_str($rawBody, $POST);
            if (!is_array($POST)) {
                $POST = [];
            }
        }

        $context->_POST = $POST;
        $context->_REQUEST = $POST === [] ? $context->_GET : array_merge($context->_GET, $POST);
    }

    /**
     * {@inheritDoc}
     */
    public function rawBody(): ?string
    {
        return $this->getContext()->rawBody;
    }

    /**
     * Return merged request input (query + post/json + route variables).
     *
     * Note: route variables are merged in {@see \Switon\Http\RequestHandler::handle()} and
     * override keys from POST/query.
     *
     * @return array<string, mixed>
     *
     * @see \Switon\Http\RequestHandler::handle()
     * @see \Switon\Binding\ArgumentsBinder::resolve()
     * @see \Switon\Http\RequestInterface::all()
     */
    public function all(): array
    {
        return $this->getContext()->_REQUEST;
    }

    /**
     * @param list<string|int> $names
     */
    public function only(array $names): array
    {
        $data = [];

        foreach ($this->all() as $name => $val) {
            if (in_array($name, $names, true)) {
                $data[$name] = $val;
            }
        }

        return $data;
    }

    /**
     * @param list<string|int> $names
     */
    public function except(array $names): array
    {
        $data = [];

        foreach ($this->all() as $name => $val) {
            if (!in_array($name, $names, true)) {
                $data[$name] = $val;
            }
        }

        return $data;
    }

    /**
     * Extract request input as query filters.
     *
     * This method preserves operator suffixes in keys (e.g. <code>age>=</code>, <code>name*=</code>)
     * and filters out empty strings/nulls. Operator parsing happens in Query/ORM.
     *
     * @param list<string> $fields
     * @param array<string, mixed> $custom
     *
     * @see \Switon\Orm\AbstractRepository::where() ORM pre-processing (e.g. field@= → field~= / >= / <=)
     * @see \Switon\Query\QueryInterface::where() Query entry
     * @see \Switon\Http\RequestInterface::filters() Contract + operator list
     * @see \Switon\Query\AbstractConditionBuilder::where() Filter-key operator parser (>=, *=, ~=, ?=, ...)
     */
    public function filters(array $fields = [], array $custom = []): array
    {
        if (!empty($fields) && !array_is_list($fields)) {
            InvalidIndexedArrayException::raise('Parameter $fields must be an indexed array, got associative array');
        }

        if (!empty($custom) && array_is_list($custom)) {
            InvalidAssociativeArrayException::raise('Parameter $custom must be an associative array, got indexed array');
        }

        $data = $this->all();
        $result = [];

        // If $fields is empty, extract all fields from request
        if (empty($fields)) {
            foreach ($data as $field => $value) {
                // Filter empty strings
                if (is_string($value)) {
                    $value = trim($value);
                    if ($value === '') {
                        continue;
                    }
                }
                $result[$field] = $value;
            }
        } else {
            // Extract specified fields (indexed array only)
            foreach ($fields as $fieldName) {
                // Extract base field name (strip operator suffix and handle relation.field format)
                preg_match('#^\w+#', ($pos = strpos($fieldName, '.')) ? substr($fieldName, $pos + 1) : $fieldName, $match);
                $field = $match[0];

                if (!isset($data[$field])) {
                    continue;
                }

                $value = $data[$field];
                // Filter empty strings and null values
                if (is_string($value)) {
                    $value = trim($value);
                    if ($value === '') {
                        continue;
                    }
                }

                // Keep the original field name (with operators if present)
                $result[$fieldName] = $value;
            }
        }

        // Merge custom values
        return array_merge($result, $custom);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string|int $name, mixed $default = null): mixed
    {
        return $this->getContext()->_REQUEST[$name] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string|int $name): bool
    {
        return isset($this->getContext()->_REQUEST[$name]);
    }

    /**
     * {@inheritDoc}
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('accept', '');
        if ($accept !== '' && $this->acceptsJson($accept)) {
            return true;
        }

        // XHR from axios/fetch (e.g. app-admin sets X-Requested-With); full page navigations omit this.
        if ($this->isAjax()) {
            return true;
        }

        // app-admin core.js appendAjaxFlag adds ?ajax / &ajax on XHR reload; first HTML GET usually has no ajax.
        return $this->has('ajax');
    }

    protected function mimeType(string $contentType): string
    {
        return strtolower(trim(($pos = strpos($contentType, ';')) === false
            ? $contentType
            : substr($contentType, 0, $pos)));
    }

    protected function isJsonContentType(string $contentType): bool
    {
        $mimeType = $this->mimeType($contentType);

        return $mimeType === 'application/json'
            || $mimeType === 'text/json'
            || str_ends_with($mimeType, '+json');
    }

    protected function acceptsJson(string $acceptHeader): bool
    {
        foreach (explode(',', $acceptHeader) as $contentType) {
            if ($this->isJsonContentType($contentType)) {
                return true;
            }
        }

        return false;
    }

    public function handler(): string
    {
        return $this->getContext()->matcher?->getHandler() ?? '';
    }

    public function route(?string $name = null, mixed $default = null): mixed
    {
        $matcher = $this->getContext()->matcher;
        if ($matcher === null) {
            return $name === null ? [] : $default;
        }

        if ($name === null) {
            return $matcher->getVariables();
        }

        return $matcher->getVariables()[$name] ?? $default;
    }

    public function query(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->getContext()->_GET;
        }
        return $this->getContext()->_GET[$name] ?? $default;
    }

    public function post(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->getContext()->_POST;
        }
        return $this->getContext()->_POST[$name] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers()[strtolower($name)] ?? $default;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers()[strtolower($name)]);
    }

    public function headers(): array
    {
        return $this->getContext()->headers;
    }

    public function server(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->getContext()->_SERVER;
        }

        return $this->getContext()->_SERVER[$name] ?? $default;
    }

    public function verb(): string
    {
        return $this->server('REQUEST_METHOD', 'GET');
    }

    public function isVerb(string $verb): bool
    {
        return $verb === $this->verb();
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with') ?? '') === 'xmlhttprequest';
    }

    public function scheme(): string
    {
        if ($scheme = $this->server('REQUEST_SCHEME')) {
            return $scheme;
        } else {
            return $this->server('HTTPS') === 'on' ? 'https' : 'http';
        }
    }

    public function host(): string
    {
        return $this->header('host') ?: $this->server('SERVER_NAME', '');
    }

    public function port(): int
    {
        // Try to get port from Host header first
        $host = $this->header('host');
        if ($host && str_contains($host, ':')) {
            if (str_starts_with($host, '[')) {
                $end = strpos($host, ']');
                if ($end !== false) {
                    $portPart = substr($host, $end + 1);
                    if (str_starts_with($portPart, ':')) {
                        $port = (int)substr($portPart, 1);
                        if ($port > 0) {
                            return $port;
                        }
                    }
                }
            } else {
                $port = (int)substr($host, strrpos($host, ':') + 1);
                if ($port > 0) {
                    return $port;
                }
            }
        }

        // Fall back to SERVER_PORT
        return (int)$this->server('SERVER_PORT', 80);
    }

    public function isSecure(): bool
    {
        return $this->scheme() === 'https';
    }

    public function ip(): string
    {
        return $this->header('x-real-ip') ?: $this->server('REMOTE_ADDR', '');
    }

    public function files(bool $onlySuccessful = true): array
    {
        $r = [];

        foreach ($this->getContext()->_FILES as $key => $files) {
            if (isset($files[0])) {
                foreach ($files as $file) {
                    $errorValue = is_int($file['error']) ? $file['error'] : (int)$file['error'];
                    if (!$onlySuccessful || $errorValue === UPLOAD_ERR_OK) {
                        $file['key'] = $key;
                        $file['error'] = $errorValue; // Normalize to int
                        $r[] = $this->maker->make(File::class, ['file' => $file]);
                    }
                }
            } elseif (is_int($files['error']) || is_string($files['error'])) {
                // Handle single file upload (error is int or string)
                $file = $files;
                $errorValue = is_int($files['error']) ? $files['error'] : (int)$files['error'];
                if (!$onlySuccessful || $errorValue === UPLOAD_ERR_OK) {
                    $file['key'] = $key;
                    $file['error'] = $errorValue; // Normalize to int
                    $r[] = $this->maker->make(File::class, ['file' => $file]);
                }
            } else {
                // Handle multiple files with same key (array-style upload)
                $countFiles = count($files['error']);
                for ($i = 0; $i < $countFiles; $i++) {
                    $errorValue = is_int($files['error'][$i]) ? $files['error'][$i] : (int)$files['error'][$i];
                    if (!$onlySuccessful || $errorValue === UPLOAD_ERR_OK) {
                        $file = [
                            'key' => $key,
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $errorValue, // Normalize to int
                            'size' => $files['size'][$i],
                        ];
                        $r[] = $this->maker->make(File::class, ['file' => $file]);
                    }
                }
            }
        }

        return $r;
    }

    public function file(?string $key = null): ?FileInterface
    {
        $files = $this->files();

        if ($key === null) {
            return $files ? current($files) : null;
        } else {
            foreach ($files as $file) {
                if ($file->getKey() === $key) {
                    return $file;
                }
            }
            return null;
        }
    }

    public function hasFile(string $key): bool
    {
        $context = $this->getContext();

        if (!isset($context->_FILES[$key])) {
            return false;
        }

        $files = $context->_FILES[$key];

        // Handle single file upload
        if (isset($files['error'])) {
            if (is_int($files['error'])) {
                return $files['error'] === UPLOAD_ERR_OK;
            } elseif (is_array($files['error'])) {
                // Handle multiple files with same key
                foreach ($files['error'] as $error) {
                    if ((int)$error === UPLOAD_ERR_OK) {
                        return true;
                    }
                }
                return false;
            } else {
                // Handle string error value
                return (int)$files['error'] === UPLOAD_ERR_OK;
            }
        }

        // Handle array of files
        if (isset($files[0])) {
            foreach ($files as $file) {
                $errorValue = is_int($file['error']) ? $file['error'] : (int)$file['error'];
                if ($errorValue === UPLOAD_ERR_OK) {
                    return true;
                }
            }
        }

        return false;
    }

    public function origin(bool $strict = true): string
    {
        if ($origin = $this->header('origin')) {
            return $origin;
        }

        if (!$strict && ($referer = $this->header('referer'))) {
            if ($pos = strpos($referer, '?')) {
                $referer = substr($referer, 0, $pos);
            }

            if ($pos = strpos($referer, '://')) {
                $pos = strpos($referer, '/', $pos + 3);
                return $pos ? substr($referer, 0, $pos) : $referer;
            }
        }

        return '';
    }

    public function url(bool $withQuery = false): string
    {
        return $this->scheme() . '://' . $this->host() . $this->path($withQuery);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return (array)$this->getContext();
    }

    public function elapsed(int $precision = 3): float
    {
        $start = $this->server('REQUEST_TIME_FLOAT');
        $start = is_numeric($start) ? (float)$start : $this->clock->microtime();
        return round($this->clock->microtime() - $start, $precision);
    }

    public function path(bool $withQuery = false): string
    {
        $requestUri = $this->server('REQUEST_URI') ?? '';

        $pos = strpos($requestUri, '?');
        $path = $pos === false ? $requestUri : substr($requestUri, 0, $pos);
        $path = $path === '/' ? '/' : rtrim($path, '/');

        if ($withQuery && $pos !== false) {
            return $path . substr($requestUri, $pos);
        }

        return $path;
    }

    public function attribute(string $name): mixed
    {
        return $this->getContext()->attributes[$name] ?? null;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->getContext()->attributes);
    }

    public function attributes(): array
    {
        return $this->getContext()->attributes;
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->getContext()->attributes[$name] = $value;
    }
}
