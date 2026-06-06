<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Exception\InvalidUrlPathException;
use Switon\Routing\RouterInterface;

use function array_key_exists;
use function array_pop;
use function explode;
use function get_debug_type;
use function http_build_query;
use function implode;
use function is_string;
use function parse_str;
use function str_contains;
use function str_starts_with;
use function strpos;
use function substr;
use function trim;

/**
 * URL generator implementation for HTTP paths and redirects.
 *
 * Use this for route-aware path composition and redirect targets.
 * Road-signs:
 * - url() validates absolute app paths before generate()
 * - action() resolves current-context relative paths before generate()
 * - string input may already contain query parameters
 * - array input uses item 0 as path and `#` as fragment
 * - router prefix is prepended before scheme/host handling
 *
 * @see \Switon\Http\UrlGeneratorInterface
 * @see \Switon\Routing\RouterInterface
 * @see \Switon\Http\RequestInterface
 * @see \Switon\Http\Response
 */
class UrlGenerator implements UrlGeneratorInterface
{
    #[Autowired] protected RouterInterface $router;
    #[Autowired] protected RequestInterface $request;

    /**
     * {@inheritDoc}
     */
    public function url(string|array $args = [], bool|string $scheme = false): string
    {
        if (is_string($args)) {
            $this->assertAbsoluteUrlPath($args);

            return $this->generate($args, $scheme);
        }

        $path = $this->assertHelperPath($args[0] ?? '', 'url');
        $this->assertAbsoluteUrlPath($path);

        if (array_key_exists(0, $args)) {
            $args[0] = $path;
        }

        return $this->generate($args, $scheme);
    }

    /**
     * {@inheritDoc}
     */
    public function action(string|array $args = [], bool|string $scheme = false): string
    {
        if (is_string($args)) {
            return $this->generate($this->resolveActionPath($args), $scheme);
        }

        $path = $this->assertHelperPath($args[0] ?? '', 'action');
        $args[0] = $this->resolveActionPath($path);

        return $this->generate($args, $scheme);
    }

    /**
     * {@inheritDoc}
     */
    public function generate(string|array $args, bool|string $scheme = false): string
    {
        if (is_string($args)) {
            if (($pos = strpos($args, '?')) !== false) {
                $path = substr($args, 0, $pos);
                parse_str(substr($args, $pos + 1), $variables);
            } else {
                $path = $args;
                $variables = [];
            }
        } else {
            $path = $args[0] ?? '';
            unset($args[0]);
            $variables = $args;
        }

        $url = $this->router->getPrefix() . $path;

        if ($variables !== []) {
            $fragment = null;
            if (isset($variables['#'])) {
                $fragment = $variables['#'];
                unset($variables['#']);
            }

            if ($variables !== []) {
                $url .= '?' . http_build_query($variables);
            }
            if ($fragment !== null) {
                $url .= "#$fragment";
            }
        }

        if ($scheme) {
            if ($scheme === true) {
                $scheme = $this->request->scheme();
            }
            return ($scheme === '//' ? '//' : "$scheme://") . $this->request->header('host') . $url;
        } else {
            return $url;
        }
    }

    /**
     * Validate helper path input before URL composition.
     */
    protected function assertHelperPath(mixed $path, string $helper): string
    {
        if (!is_string($path)) {
            InvalidUrlPathException::raise(
                '{helper}() path must be a string, got {type}.',
                ['helper' => $helper, 'type' => get_debug_type($path)]
            );
        }

        return $path;
    }

    /**
     * Keep url() limited to absolute app paths.
     */
    protected function assertAbsoluteUrlPath(string $path): void
    {
        if ($path !== '' && !str_starts_with($path, '/')) {
            InvalidUrlPathException::raise(
                'url() path must start with "/". Use action() for current-context relative paths: "{path}".',
                ['path' => $path]
            );
        }
    }

    /**
     * Resolve action() input against the current request path.
     */
    protected function resolveActionPath(string $path): string
    {
        $query = '';
        if (str_contains($path, '?')) {
            [$path, $query] = explode('?', $path, 2);
            $query = '?' . $query;
        }

        if ($path === '') {
            return $this->currentAppPath() . $query;
        }

        if (str_starts_with($path, '/')) {
            return $path . $query;
        }

        $basePath = $this->parentPath($this->currentAppPath());

        return $this->normalizeAbsolutePath($basePath . '/' . $path) . $query;
    }

    /**
     * Strip router prefix from the current request path.
     */
    protected function currentAppPath(): string
    {
        $requestPath = $this->request->path();
        $requestPath = $requestPath === '' ? '/' : $requestPath;
        $prefix = $this->router->getPrefix($requestPath);

        if ($prefix !== '' && str_starts_with($requestPath, $prefix)) {
            $appPath = substr($requestPath, strlen($prefix));

            return $appPath === '' ? '/' : $appPath;
        }

        return $requestPath;
    }

    /**
     * Return the parent app path for current-context link generation.
     */
    protected function parentPath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        $segments = explode('/', trim($path, '/'));
        array_pop($segments);

        return $segments === [] ? '/' : '/' . implode('/', $segments);
    }

    /**
     * Normalize "." and ".." segments into an absolute app path.
     */
    protected function normalizeAbsolutePath(string $path): string
    {
        $normalized = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);
                continue;
            }

            $normalized[] = $segment;
        }

        return $normalized === [] ? '/' : '/' . implode('/', $normalized);
    }
}
