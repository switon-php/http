<?php

declare(strict_types=1);

namespace Switon\Http;

use Psr\EventDispatcher\EventDispatcherInterface;
use Stringable;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ContextAware;
use Switon\Core\ContextManagerInterface;
use Switon\Core\Lazy;
use Switon\Core\PathAliasInterface;
use Switon\Core\StopFlow;
use Switon\Http\Response\JsonRendererInterface;

use function time;

/**
 * HTTP response state writer backed by ResponseContext.
 *
 * Use this as the mutable response facade during request finalization.
 * Road-signs:
 * - set status / headers / cookies / content on ResponseContext
 * - redirect throws StopFlow after Location is prepared
 * - json / text / raw are high-level body writers
 * - sendHeaders / sendBody live on ServerInterface
 * - renderer interfaces own JSON / XML / CSV body shaping
 *
 * @see \Switon\Http\ResponseInterface
 * @see \Switon\Http\ResponseContext
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\Response\JsonRendererInterface
 * @see \Switon\Http\Response\XmlRendererInterface
 * @see \Switon\Http\Response\CsvRendererInterface
 */
class Response implements ResponseInterface, ContextAware
{
    #[Autowired] protected ContextManagerInterface $contextManager;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected UrlGeneratorInterface|Lazy $urlGenerator;
    #[Autowired] protected ServerInterface|Lazy $server;
    #[Autowired] protected JsonRendererInterface $jsonRenderer;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected PathAliasInterface $pathAlias;

    /**
     * Return the response context for the current request scope.
     */
    public function getContext(): ResponseContext
    {
        return $this->contextManager->getContext($this);
    }

    public function setCookie(
        string  $name,
        mixed   $value,
        int     $expire = 0,
        ?string $path = null,
        ?string $domain = null,
        bool    $secure = false,
        bool    $httponly = true
    ): static {
        $context = $this->getContext();

        if ($expire > 0) {
            $current = time();
            if ($expire < $current) {
                $expire += $current;
            }
        }

        $context->cookies[$name] = [
            'name' => $name,
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly
        ];

        return $this;
    }

    /** @return array<string, array<string, mixed>> */
    public function getCookies(): array
    {
        return $this->getContext()->cookies;
    }

    public function setStatus(int $code, ?string $text = null): static
    {
        $context = $this->getContext();

        $context->status_code = $code;
        $context->status_text = $text ?: $this->getStatusText($code);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): string
    {
        $context = $this->getContext();

        return $context->status_code . ' ' . $context->status_text;
    }

    /**
     * {@inheritDoc}
     */
    public function getStatusCode(): int
    {
        return $this->getContext()->status_code;
    }

    /**
     * {@inheritDoc}
     */
    public function getStatusText(?int $code = null): string
    {
        if ($code === null) {
            return $this->getContext()->status_text;
        } else {
            $texts = [
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',
                207 => 'Multi-Status',
                208 => 'Already Reported',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',
                308 => 'Permanent Redirect',
                400 => 'Bad Request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Time-out',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested range unsatisfiable',
                417 => 'Expectation failed',
                418 => 'I\'m a teapot',
                421 => 'Misdirected Request',
                422 => 'Unprocessable entity',
                423 => 'Locked',
                424 => 'Method failure',
                425 => 'Unordered Collection',
                426 => 'Upgrade Required',
                428 => 'Precondition Required',
                429 => 'Too Many Requests',
                431 => 'Request Header Fields Too Large',
                449 => 'Retry With',
                450 => 'Blocked by Windows Parental Controls',
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway or Proxy Error',
                503 => 'Service Unavailable',
                504 => 'Gateway Time-out',
                505 => 'HTTP Version not supported',
                507 => 'Insufficient storage',
                508 => 'Loop Detected',
                509 => 'Bandwidth Limit Exceeded',
                510 => 'Not Extended',
                511 => 'Network Authentication Required',
            ];

            return $texts[$code] ?? 'App Error';
        }
    }

    public function setHeader(string $name, string $value): static
    {
        $context = $this->getContext();

        $context->headers[$name] = $value;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        $context = $this->getContext();

        return $context->headers[$name] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function hasHeader(string $name): bool
    {
        $context = $this->getContext();

        return isset($context->headers[$name]);
    }

    /** @param string|array<string, mixed> $location */
    public function redirect(string|array $location, bool $temporarily = true): static
    {
        if ($temporarily) {
            $this->setStatus(302, 'Temporarily Moved');
        } else {
            $this->setStatus(301, 'Permanently Moved');
        }

        $url = $this->urlGenerator->generate($location);
        $this->setHeader('Location', $url);

        throw StopFlow::because('Redirecting to: {url}', ['url' => $url]);
    }

    public function setContent(mixed $content): static
    {
        $context = $this->getContext();

        $context->content = $content;

        return $this;
    }

    public function json(mixed $content, int $status = 200, int $options = 0): static
    {
        $this->setStatus($status);
        $this->jsonRenderer->render($content, $options);

        return $this;
    }

    public function raw(mixed $content, string $contentType, int $status = 200): static
    {
        $this->setStatus($status);
        $this->setHeader('Content-Type', $contentType);
        $this->setContent($content);

        return $this;
    }

    public function text(string $content, string $contentType = 'text/plain', int $status = 200): static
    {
        $this->setStatus($status);
        $this->setHeader('Content-Type', $contentType . '; charset=utf-8');
        $this->setContent($content);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): mixed
    {
        return $this->getContext()->content;
    }

    /**
     * {@inheritDoc}
     */
    public function hasContent(): bool
    {
        $context = $this->getContext();

        $content = $context->content;

        return $content !== '' && $content !== null;
    }

    public function setFile(string $file): static
    {
        $context = $this->getContext();

        $context->file = $this->pathAlias->resolve($file);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getFile(): ?string
    {
        return $this->getContext()->file;
    }

    /**
     * {@inheritDoc}
     */
    public function hasFile(): bool
    {
        $context = $this->getContext();

        return (bool)$context->file;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->getContext()->headers;
    }

    /**
     * {@inheritDoc}
     */
    public function isChunked(): bool
    {
        return $this->getContext()->chunked;
    }

    /**
     * {@inheritDoc}
     */
    public function write(string|Stringable $chunk): bool
    {
        $context = $this->getContext();

        if (!$context->chunked) {
            $context->chunked = true;

            $this->setHeader('Transfer-Encoding', 'chunked');
            $this->server->sendHeaders();
        }

        return $this->server->write((string)$chunk);
    }
}
