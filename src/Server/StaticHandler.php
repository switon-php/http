<?php

declare(strict_types=1);

namespace Switon\Http\Server;

use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Routing\RouterInterface;

use function count;
use function in_array;
use function is_file;
use function realpath;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;

/**
 * Resolves static files under public locations and provides MIME type mapping.
 *
 * Guidance: Use this only for static-asset short-circuiting; router dispatch should handle dynamic requests.
 *
 * Road-signs:
 * - getLocations() discovers top-level public paths
 * - getFileInternal() filters URIs by router prefix and public location
 * - getFile() resolves and constrains the final path under @public
 * - getMimeType() maps file extension to response type
 *
 * @see \Switon\Http\Server\StaticHandlerInterface
 * @see \Switon\Routing\RouterInterface
 * @see \Switon\Core\PathAliasInterface
 * @see \Switon\Http\Server\Adapter\Php
 * @see \Switon\Http\Server\Adapter\Swoole
 */
class StaticHandler implements StaticHandlerInterface
{
    #[Autowired] protected PathAliasInterface $pathAlias;
    #[Autowired] protected RouterInterface $router;
    #[Autowired] protected FilesystemInterface $filesystem;

    /** @var list<string> */
    protected array $locations;
    /** @var array<string, string> */
    protected array $mime_types;

    /**
     * @param list<string>|null $locations
     */
    public function __construct(?array $locations = null)
    {
        $this->locations = $locations ?? $this->getLocations();
        $this->mime_types = $this->getMimeTypes();
    }

    /**
     * @return list<string>
     */
    protected function getLocations(): array
    {
        $locations = [];
        foreach ($this->filesystem->glob('@public/*') as $file) {
            $file = basename($file);
            if (str_starts_with($file, '.') || pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                continue;
            }

            $locations[] = '/' . basename($file);
        }

        return $locations;
    }

    /**
     * @return array<string, string>
     */
    protected function getMimeTypes(): array
    {
        $mime_types = [];
        $lines = preg_split('/\R/', $this->filesystem->read('@switon.http.resources/Server/mime.types')) ?: [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (!str_contains($line, ';')) {
                continue;
            }

            $line = trim($line);
            $line = trim($line, ';');

            $parts = preg_split('#\s+#', $line, -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($parts) || count($parts) < 2) {
                continue;
            }

            foreach ($parts as $k => $part) {
                if ($k !== 0) {
                    $mime_types[$part] = $parts[0];
                }
            }
        }

        return $mime_types;
    }

    protected function getFileInternal(string $uri): ?string
    {
        $file = ($pos = strpos($uri, '?')) === false ? $uri : substr($uri, 0, $pos);

        $prefix = $this->router->getPrefix();
        if ($file === $prefix || !str_starts_with($file, $prefix)) {
            return null;
        }

        $file = substr($file, strlen($prefix));

        if (in_array($file, $this->locations, true)) {
            return $file;
        } elseif (($pos = strpos($file, '/', 1)) === false) {
            return null;
        } else {
            $level1 = substr($file, 0, $pos);
            return in_array($level1, $this->locations, true) ? $file : null;
        }
    }

    public function isFile(string $uri): bool
    {
        return $this->getFile($uri) !== null;
    }

    public function getMimeType(string $file): string
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return $this->mime_types[$ext] ?? 'application/octet-stream';
    }

    public function getFile(string $uri): ?string
    {
        $fileInternal = $this->getFileInternal($uri);
        if ($fileInternal === null) {
            return null;
        }

        $publicRoot = realpath($this->pathAlias->resolve('@public'));
        if ($publicRoot === false) {
            return null;
        }

        // Remove leading slash from fileInternal to avoid double slashes when concatenating with '@public/'
        $file = $this->pathAlias->resolve('@public/' . ltrim($fileInternal, '/'));
        $resolvedFile = realpath($file);
        if ($resolvedFile === false) {
            return null;
        }

        $publicRoot = rtrim(str_replace('\\', '/', $publicRoot), '/');
        $resolvedFile = str_replace('\\', '/', $resolvedFile);
        if ($resolvedFile !== $publicRoot && !str_starts_with($resolvedFile, $publicRoot . '/')) {
            return null;
        }

        return is_file($resolvedFile) ? $resolvedFile : null;
    }
}
