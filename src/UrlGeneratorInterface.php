<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * URL generation boundary for HTTP responses and redirects.
 *
 * Guidance: Use url() for absolute app paths and action() for current-context relative paths.
 *
 * Road-signs:
 * - url(): absolute app path only; relative strings are rejected
 * - action(): current-context relative path or absolute app path
 * - string input: path or path-with-query
 * - array input: first item path, remaining items query params, `#` becomes fragment
 * - router prefix is applied to relative app paths
 * - scheme=false keeps relative URL; true/current string/'//' makes absolute or protocol-relative URL
 *
 * @see \Switon\Http\UrlGenerator
 * @see \Switon\Routing\RouterInterface
 * @see \Switon\Http\RequestInterface
 * @see \Switon\Http\Response
 */
interface UrlGeneratorInterface
{
    /**
     * Generate one URL from an absolute app path.
     *
     * @param string|array<int|string, mixed> $args
     */
    public function url(string|array $args = [], bool|string $scheme = false): string;

    /**
     * Generate one URL from the current request context.
     *
     * @param string|array<int|string, mixed> $args
     */
    public function action(string|array $args = [], bool|string $scheme = false): string;

    /**
     * Generate one URL from path/query input and optional scheme mode.
     *
     * @param string|array<int|string, mixed> $args
     */
    public function generate(string|array $args, bool|string $scheme = false): string;
}
