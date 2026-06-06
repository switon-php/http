<?php

declare(strict_types=1);

namespace Switon\Http\Filter;

use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;
use Switon\Core\Json;
use Switon\Core\PathAliasInterface;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\Event\RequestEnd;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;

use function date;
use function is_string;
use function microtime;
use function round;
use function sprintf;

/**
 * Records slow requests that exceed the configured execution threshold.
 *
 * Guidance: Use this for latency diagnosis only; long-term request analytics belong in external observability tooling.
 *
 * Road-signs:
 * - listens on RequestEnd
 * - uses X-Response-Time when present, else request elapsed time
 * - writes one structured line when threshold is exceeded
 *
 * @see \Switon\Http\Event\RequestEnd
 * @see \Switon\Core\FilesystemInterface
 * @see \Switon\Core\PathAliasInterface
 */
class SlowlogFilter
{
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected PathAliasInterface $pathAlias;

    #[Autowired] protected float $threshold = 1.0;
    #[Autowired] protected string $file = '@runtime/slowlog/{app_id}.log';
    #[Autowired] protected string $format = '[:date][:client_ip][:request_id][:elapsed] :message';

    protected function write(float $elapsed, mixed $message): void
    {
        $elapsed = round($elapsed, 3);

        if (!is_string($message)) {
            $message = Json::stringify($message, JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        $replaced = [];
        $ts = microtime(true);
        $replaced[':date'] = date('Y-m-d\TH:i:s', (int)$ts) . sprintf('.%03d', ($ts - (int)$ts) * 1000);
        $replaced[':client_ip'] = $this->request->ip();
        $replaced[':request_id'] = $this->request->header('x-request-id', '');
        $replaced[':elapsed'] = sprintf('%.03f', $elapsed);
        $replaced[':message'] = $message . PHP_EOL;

        $this->filesystem->append($this->pathAlias->resolve($this->file, ['app_id' => $this->app->id()]), strtr($this->format, $replaced));
    }

    #[EventListener] public function onEnd(RequestEnd $event): void
    {
        if ($event->response->hasHeader('X-Response-Time')) {
            $elapsed = (float)$event->response->getHeader('X-Response-Time');
        } else {
            $elapsed = $event->request->elapsed();
        }

        if ($this->threshold > $elapsed) {
            return;
        }

        $message = [
            'method' => $this->request->verb(),
            'handler' => $this->request->handler(),
            'url' => $this->request->url(),
            '_REQUEST' => $this->request->all(),
            'elapsed' => $elapsed,
        ];

        $this->write($elapsed, $message);
    }
}
