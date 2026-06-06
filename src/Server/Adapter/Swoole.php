<?php

declare(strict_types=1);

namespace Switon\Http\Server\Adapter;

use ReflectionClass;
use ReflectionMethod;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ContextAware;
use Switon\Core\ContextManagerInterface;
use Switon\Core\Lazy;
use Switon\Core\Runtime as CoreRuntime;
use Switon\Eventing\Event\DispatchFailed;
use Switon\Http\AbstractServer;
use Switon\Http\Event\AssetSending;
use Switon\Http\Event\AssetSent;
use Switon\Http\Event\BodySending;
use Switon\Http\Event\BodySent;
use Switon\Http\Event\ChunkWriting;
use Switon\Http\Event\ChunkWritten;
use Switon\Http\Event\HeadersSending;
use Switon\Http\Event\HeadersSent;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Server\Attribute\ServerCallback;
use Switon\Http\Server\Event\ServerBeforeShutdown;
use Switon\Http\Server\Event\ServerClose;
use Switon\Http\Server\Event\ServerConnect;
use Switon\Http\Server\Event\ServerFinish;
use Switon\Http\Server\Event\ServerManagerStart;
use Switon\Http\Server\Event\ServerManagerStop;
use Switon\Http\Server\Event\ServerPacket;
use Switon\Http\Server\Event\ServerPipeMessage;
use Switon\Http\Server\Event\ServerReady;
use Switon\Http\Server\Event\ServerShutdown;
use Switon\Http\Server\Event\ServerStart;
use Switon\Http\Server\Event\ServerStarted;
use Switon\Http\Server\Event\ServerTask;
use Switon\Http\Server\Event\ServerTaskerError;
use Switon\Http\Server\Event\ServerTaskerExit;
use Switon\Http\Server\Event\ServerTaskerStart;
use Switon\Http\Server\Event\ServerTaskerStop;
use Switon\Http\Server\Event\ServerWorkerError;
use Switon\Http\Server\Event\ServerWorkerExit;
use Switon\Http\Server\Event\ServerWorkerStart;
use Switon\Http\Server\Event\ServerWorkerStop;
use Switon\Http\Server\Network;
use Switon\Http\Server\StaticHandlerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Runtime;
use Throwable;

use function basename;
use function dirname;
use function in_array;
use function round;
use function strlen;
use function strtoupper;
use function substr;
use function defined;

use const SWOOLE_VERSION;

/**
 * Runs the Swoole HTTP server and maps transport callbacks to framework events.
 *
 * Guidance: Keep coroutine transport and callback wiring here; request normalization still starts at RequestReceived and RequestHandler.
 *
 * Road-signs:
 * - per-request globals emit RequestReceived
 * - static assets short-circuit via StaticHandlerInterface
 * - ServerCallback maps Swoole lifecycle to events
 * - dispatch failures degrade to DispatchFailed
 * - request state lives in SwooleContext
 *
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\AbstractServer
 * @see \Switon\Http\Event\RequestReceived
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Http\Server\StaticHandlerInterface
 * @see \Switon\Http\Server\Attribute\ServerCallback
 * @see \Switon\Eventing\Event\DispatchFailed
 * @see \Switon\Http\Server\Adapter\SwooleContext
 */
class Swoole extends AbstractServer implements ContextAware
{
    #[Autowired] protected ContextManagerInterface $contextManager;
    #[Autowired] protected StaticHandlerInterface|Lazy $staticHandler;

    /** @var array<string, mixed> Swoole server settings */
    #[Autowired] protected array $settings = [];

    /** @var array<string, mixed> */
    protected array $_SERVER;

    public function __construct(AppInterface $app)
    {
        parent::__construct($app);

        $script_filename = get_included_files()[0];
        $document_root = dirname($script_filename);
        $_SERVER['DOCUMENT_ROOT'] = $document_root;

        $swooleVersion = defined('SWOOLE_VERSION') ? SWOOLE_VERSION : 'n/a';

        $this->_SERVER = [
            'DOCUMENT_ROOT' => $document_root,
            'SCRIPT_FILENAME' => $script_filename,
            'SCRIPT_NAME' => '/' . basename($script_filename),
            'SERVER_ADDR' => $this->serverOptions->host === '0.0.0.0' ? Network::local() : $this->serverOptions->host,
            'SERVER_PORT' => $this->serverOptions->port,
            'SERVER_SOFTWARE' => 'Swoole/' . $swooleVersion . ' (' . PHP_OS . ') PHP/' . PHP_VERSION,
            'PHP_SELF' => '/' . basename($script_filename),
            'QUERY_STRING' => '',
            'REQUEST_SCHEME' => 'http',
        ];

        $this->settings['enable_coroutine'] = CoreRuntime::isCoroutineEnabled();

        if (isset($this->settings['max_request']) && $this->settings['max_request'] < 1) {
            $this->settings['max_request'] = 1;
        }

        if (!empty($this->settings['enable_static_handler'])) {
            $this->settings['document_root'] = $document_root;
        }
    }

    public function getContext(): SwooleContext
    {
        return $this->contextManager->getContext($this);
    }

    protected function prepareGlobals(Request $request): void
    {
        $_server = array_change_key_case($request->server, CASE_UPPER);
        unset($_server['SERVER_SOFTWARE']);

        foreach ($request->header ?: [] as $k => $v) {
            if (in_array($k, ['content-type', 'content-length'], true)) {
                $_server[strtoupper(strtr($k, '-', '_'))] = $v;
            } else {
                $_server['HTTP_' . strtoupper(strtr($k, '-', '_'))] = $v;
            }
        }

        $_server += $this->_SERVER;

        $_get = $request->get ?: [];
        $_post = $request->post ?: [];
        $raw_body = $request->rawContent();
        if ($raw_body === false) {
            $raw_body = null;
        }
        $cookies = $request->cookie ?? [];
        $files = $request->files ?? [];

        $this->eventDispatcher->dispatch(new RequestReceived($_get, $_post, $_server, $raw_body, $cookies, $files));
    }

    protected function dispatchEvent(object $object): void
    {
        try {
            $this->eventDispatcher->dispatch($object);
        } catch (Throwable $throwable) {
            // Dispatch error event directly (not through dispatchEvent) to avoid infinite loops
            try {
                $this->eventDispatcher->dispatch(DispatchFailed::from($object, $throwable));
            } catch (Throwable) {
                // If event dispatch for error also fails, silently ignore to prevent infinite loops
            }
        }
    }

    #[ServerCallback]
    public function onStart(Server $server): void
    {
        $this->dispatchEvent(new ServerStart($server));

        $elapsed = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3);
        $this->eventDispatcher->dispatch(new ServerStarted(
            '🌿 App started',
            $server,
            $elapsed,
            $this->serverOptions->host,
            $this->serverOptions->port,
            $this->app->env(),
            PHP_VERSION,
            SWOOLE_VERSION,
            $server->setting['worker_num'],
            $server->master_pid
        ));
    }

    #[ServerCallback]
    public function onBeforeShutdown(Server $server): void
    {
        $this->dispatchEvent(new ServerBeforeShutdown($server));
    }

    #[ServerCallback]
    public function onShutdown(Server $server): void
    {
        $this->dispatchEvent(new ServerShutdown($server));
    }

    #[ServerCallback]
    public function onManagerStart(Server $server): void
    {
        $this->dispatchEvent(new ServerManagerStart($server));
    }

    #[ServerCallback]
    public function onWorkerStart(Server $server, int $worker_id): void
    {
        $worker_num = $server->setting['worker_num'];
        if ($worker_id >= $worker_num) {
            $tasker_id = $worker_id - $worker_num;
            $this->dispatchEvent(new ServerTaskerStart($server, $worker_id, $tasker_id));
        } else {
            $this->dispatchEvent(new ServerWorkerStart($server, $worker_id, $worker_num));
        }
    }

    #[ServerCallback]
    public function onWorkerStop(Server $server, int $worker_id): void
    {
        $worker_num = $server->setting['worker_num'];
        if ($worker_id >= $worker_num) {
            $tasker_id = $worker_id - $worker_num;
            $this->dispatchEvent(new ServerTaskerStop($server, $worker_id, $tasker_id));
        } else {
            $this->dispatchEvent(new ServerWorkerStop($server, $worker_id, $worker_num));
        }
    }

    #[ServerCallback]
    public function onWorkerExit(Server $server, int $worker_id): void
    {
        $worker_num = $server->setting['worker_num'];
        if ($worker_id >= $worker_num) {
            $tasker_id = $worker_id - $worker_num;
            $this->dispatchEvent(new ServerTaskerExit($server, $worker_id, $tasker_id));
        } else {
            $this->dispatchEvent(new ServerWorkerExit($server, $worker_id, $worker_num));
        }
    }

    #[ServerCallback]
    public function onConnect(Server $server, int $fd, int $reactor_id): void
    {
        $this->dispatchEvent(new ServerConnect($server, $fd, $reactor_id));
    }

    /**
     * @param array<string, mixed> $client
     */
    #[ServerCallback]
    public function onPacket(Server $server, string $data, array $client): void
    {
        $this->dispatchEvent(new ServerPacket($server, $data, $client));
    }

    #[ServerCallback]
    public function onClose(Server $server, int $fd, int $reactor_id): void
    {
        $this->dispatchEvent(new ServerClose($server, $fd, $reactor_id));
    }

    #[ServerCallback]
    public function onTask(Server $server, int $worker_id, int $src_worker_id, mixed $data): void
    {
        $this->dispatchEvent(new ServerTask($server, $worker_id, $src_worker_id, $data));
    }

    #[ServerCallback]
    public function onFinish(Server $server, int $worker_id, mixed $data): void
    {
        $this->dispatchEvent(new ServerFinish($server, $worker_id, $data));
    }

    #[ServerCallback]
    public function onPipeMessage(Server $server, int $src_worker_id, mixed $message): void
    {
        $this->dispatchEvent(new ServerPipeMessage($server, $src_worker_id, $message));
    }

    #[ServerCallback]
    public function onWorkerError(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal): void
    {
        $worker_num = $server->setting['worker_num'];
        if ($worker_id >= $worker_num) {
            $tasker_id = $worker_id - $worker_num;
            $this->dispatchEvent(new ServerTaskerError($server, $worker_id, $tasker_id, $worker_pid, $exit_code, $signal));
        } else {
            $this->dispatchEvent(new ServerWorkerError($server, $worker_id, $worker_pid, $exit_code, $signal));
        }
    }

    #[ServerCallback]
    public function onManagerStop(Server $server): void
    {
        $this->dispatchEvent(new ServerManagerStop($server));
    }

    protected function registerServerCallbacks(Server $server): void
    {
        $rClass = new ReflectionClass($this);

        foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(ServerCallback::class) !== []) {
                $name = $method->getName();
                $server->on(substr($name, 2), [$this, $name]);
            }
        }
    }

    public function start(): void
    {
        if (CoreRuntime::isCoroutineEnabled()) {
            Runtime::enableCoroutine();
        }

        $server = new Server($this->serverOptions->host, $this->serverOptions->port);
        $server->set($this->settings);
        $this->registerServerCallbacks($server);

        $this->dispatchEvent(new ServerReady($server, $this->serverOptions->host, $this->serverOptions->port, $this->settings));
        $server->start();
    }

    #[ServerCallback]
    public function onRequest(Request $request, Response $response): void
    {
        $uri = $request->server['request_uri'];
        if ($uri === '/favicon.ico') {
            $response->status(404);
            $response->end();
            return;
        }

        $this->prepareGlobals($request);

        if (!empty($this->settings['enable_static_handler']) && $this->staticHandler->isFile($uri)) {
            $this->dispatchEvent(new AssetSending($uri));

            $statusCode = 404;
            if (($file = $this->staticHandler->getFile($uri)) !== null) {
                $response->header('Content-Type', $this->staticHandler->getMimeType($file));
                $response->sendfile($file);
                $statusCode = 200;
            } else {
                $response->status(404, 'Not Found');
                $response->end('');
            }

            $this->dispatchEvent(new AssetSent($uri, $statusCode));
        } else {
            $context = $this->getContext();

            $context->response = $response;

            try {
                $this->requestHandler->handle();
            } catch (Throwable $throwable) {
                echo $this->formatException($throwable);
            }
        }

        $this->contextManager->resetContexts();
    }

    public function sendHeaders(): void
    {
        $this->eventDispatcher->dispatch(new HeadersSending($this->response));

        $context = $this->getContext();

        $response = $context->response;

        $http_code = $this->response->getStatusCode();
        $reason = $this->response->getStatusText($http_code);
        $response->status($http_code, $reason);

        foreach ($this->response->getHeaders() as $name => $value) {
            $response->header($name, $value, false);
        }

        $prefix = $this->router->getPrefix();
        foreach ($this->response->getCookies() as $cookie) {
            $path = $cookie['path'] ?? '';
            $response->cookie(
                $cookie['name'],
                $cookie['value'],
                $cookie['expire'],
                $path === '' ? '' : ($prefix . $path),
                $cookie['domain'] ?? '',
                $cookie['secure'],
                $cookie['httponly']
            );
        }

        $this->eventDispatcher->dispatch(new HeadersSent($this->response));
    }

    public function sendBody(): void
    {
        $context = $this->getContext();

        $response = $context->response;

        $content = $this->response->getContent() ?? '';
        if ($this->response->getStatusCode() === 304) {
            $response->end('');
        } elseif ($this->request->isVerb('HEAD')) {
            $response->header('Content-Length', (string)strlen($content), false);
            $response->end('');
        } elseif ($file = $this->response->getFile()) {
            $response->sendfile($file);
        } else {
            $event = new BodySending($this->response, $content);
            $this->eventDispatcher->dispatch($event);

            // Use potentially modified content from event
            $content = $event->content;

            $result = $response->end($content);

            $this->eventDispatcher->dispatch(new BodySent($this->response, $content, $result !== false));
        }
    }

    public function write(string $chunk): bool
    {
        $context = $this->getContext();

        $event = new ChunkWriting($this->response, $chunk);
        $this->eventDispatcher->dispatch($event);

        // Use potentially modified chunk from event
        $chunk = $event->chunk;

        if ($chunk === '') {
            $result = $context->response->end();
        } else {
            $result = $context->response->write($chunk);
        }

        $this->eventDispatcher->dispatch(new ChunkWritten($this->response, $chunk, $result));

        return $result;
    }
}
