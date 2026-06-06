<?php

declare(strict_types=1);

namespace Switon\Http;

use Stringable;

/**
 * HTTP response write boundary.
 *
 * Guidance: Mutate ResponseInterface; let ServerInterface send headers, body, or chunks.
 *
 * Road-signs:
 * - status + headers: setStatus / setHeader
 * - body builders: json / raw / text / setContent
 * - redirects: redirect
 * - file or chunks: setFile / write
 * - sender boundary: ServerInterface
 *
 * @see \Switon\Http\Response
 * @see \Switon\Http\RequestInterface
 * @see \Switon\Http\ResponseContext
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\Response\JsonRendererInterface
 * @see \Switon\Http\Response\XmlRendererInterface
 * @see \Switon\Http\Response\CsvRendererInterface
 * @see \Switon\Http\Response\AttachmentRendererInterface
 */
interface ResponseInterface
{
    /**
     * Queue a cookie for the response.
     *
     * Expire values in the past are treated as relative offsets when positive.
     */
    public function setCookie(
        string  $name,
        mixed   $value,
        int     $expire = 0,
        ?string $path = null,
        ?string $domain = null,
        bool    $secure = false,
        bool    $httponly = true
    ): static;

    /**
     * @return array<string, array<string, mixed>>
     * Return queued response cookies.
     */
    public function getCookies(): array;

    /**
     * Set status code and optional reason phrase.
     */
    public function setStatus(int $code, ?string $text = null): static;

    /**
     * Return the current status line.
     */
    public function getStatus(): string;

    /**
     * Return the current status code.
     */
    public function getStatusCode(): int;

    /**
     * Return the reason phrase for a given code, or the current one.
     */
    public function getStatusText(?int $code = null): string;

    /**
     * Set one response header.
     */
    public function setHeader(string $name, string $value): static;

    /**
     * Read one response header.
     */
    public function getHeader(string $name, ?string $default = null): ?string;

    /**
     * Check response header presence.
     */
    public function hasHeader(string $name): bool;

    /**
     * @param string|array<string, mixed> $location
     * Prepare a redirect and stop the current request flow.
     */
    public function redirect(string|array $location, bool $temporarily = true): static;

    /**
     * Set raw response content state.
     */
    public function setContent(mixed $content): static;

    /**
     * Render JSON content and set response state.
     */
    public function json(mixed $content, int $status = 200, int $options = 0): static;

    /**
     * Set raw body with explicit content type.
     */
    public function raw(mixed $content, string $contentType, int $status = 200): static;

    /**
     * Set text body and append charset to the content type.
     */
    public function text(string $content, string $contentType = 'text/plain', int $status = 200): static;

    /**
     * Return current response content.
     */
    public function getContent(): mixed;

    /**
     * Check whether response content is non-empty.
     */
    public function hasContent(): bool;

    /**
     * Set a file to be sent as the response body.
     */
    public function setFile(string $file): static;

    /**
     * Return the pending response file path.
     */
    public function getFile(): ?string;

    /**
     * Check whether a response file is set.
     */
    public function hasFile(): bool;

    /**
     * @return array<string, string>
     * Return all response headers.
     */
    public function getHeaders(): array;

    /**
     * Check whether chunked transfer mode is active.
     */
    public function isChunked(): bool;

    /**
     * Write one chunk in chunked transfer mode.
     */
    public function write(string|Stringable $chunk): bool;
}
