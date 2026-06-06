<?php

declare(strict_types=1);

namespace Switon\Http\Filter;

use DateTimeInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;
use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\ObservabilityProbe;
use Switon\Http\CookiesInterface;
use Switon\Http\Event\RequestEnd;
use Switon\Http\Filter\Event\AccessLogWriteFailed;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Throwable;

use function date;
use function preg_replace_callback;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;

/**
 * Writes structured access logs for completed HTTP requests.
 *
 * Guidance: Keep the format stable and append-only; this filter is for request observability, not business audit trails.
 *
 * Road-signs:
 * - listens on RequestEnd
 * - expands `$name` placeholders from request/response state
 * - write failures emit AccessLogWriteFailed
 *
 * @see \Switon\Http\Event\RequestEnd
 * @see \Switon\Http\Filter\Event\AccessLogWriteFailed
 * @see \Switon\Core\FilesystemInterface
 */
class AccessLogFilter implements ObservabilityProbe
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected CookiesInterface $cookies;
    #[Autowired] protected FilesystemInterface $filesystem;

    #[Autowired] protected bool $enabled = true;
    #[Autowired] protected string $default = '';
    #[Autowired] protected string $file = '@runtime/accessLog/access.log';
    #[Autowired] protected string $format = '
time=$time_iso8601
 client_ip=$client_ip
 status=$status
 request_time=$request_time
 request_method=$request_method
 request_url="$request_uri$is_args$query_string"
 request_path=$request_path
 body_bytes_sent=$body_bytes_sent
 http_referer="$http_referer"
 http_user_agent="$http_user_agent"
 http_x_forwarded_for="$http_x_forwarded_for"
 remote_addr=$remote_addr
 ';

    public function __construct()
    {
        $this->format = str_replace(["\r", "\n"], '', $this->format);
    }

    /**
     * Log access information after request completes.
     */
    #[EventListener] public function onEnd(RequestEnd $event): void
    {
        if ($this->enabled) {
            try {
                $content = preg_replace_callback('#\$(\w+)#', function ($matches) {
                    return $this->getVar($matches[1]);
                }, $this->format);

                $this->filesystem->append($this->file, $content . PHP_EOL);
            } catch (Throwable $throwable) {
                $this->eventDispatcher->dispatch(AccessLogWriteFailed::from($this->file, $throwable));
            }
        }
    }

    protected function getVar(string $name): string
    {
        if (str_starts_with($name, 'request_')) {
            if ($name === 'request_method') {
                return $this->request->verb();
            } elseif ($name === 'request_uri') {
                return (string)($this->request->server('REQUEST_URI') ?? $this->default); // Include path and query string
            } elseif ($name === 'request_url') {
                return $this->request->url();
            } elseif ($name === 'request_time') {
                return sprintf('%.3f', $this->request->elapsed());
            } elseif ($name === 'request_handler') {
                return $this->request->handler();
            } else {
                return $this->default;
            }
        } elseif (str_starts_with($name, 'http_')) {
            return (string)$this->request->header(str_replace('_', '-', substr($name, 5)), $this->default);
        } elseif (str_starts_with($name, 'cookie_')) {
            return $this->cookies->get(substr($name, 7), $this->default);
        } elseif (str_starts_with($name, 'arg_')) {
            return $this->request->get(substr($name, 4), $this->default);
        } elseif ($name === 'client_ip') {
            return $this->request->ip();
        } elseif ($name === 'time_iso8601' || $name === 'time') {
            return date(DateTimeInterface::ATOM);
        } elseif ($name === 'status') {
            return (string)$this->response->getStatusCode();
        } elseif ($name === 'body_bytes_sent') {
            return (string)strlen($this->response->getContent() ?? '');
        } elseif ($name === 'is_args') {
            return $this->request->server('QUERY_STRING', '') === '' ? '' : '?';
        } elseif ($name === 'query_string') {
            return (string)($this->request->server('QUERY_STRING') ?? '');
        } elseif (($server = $this->request->server(strtoupper($name))) !== null) {
            return (string)$server;
        } else {
            return $this->default;
        }
    }
}
