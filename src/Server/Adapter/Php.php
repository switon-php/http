<?php

declare(strict_types=1);

namespace Switon\Http\Server\Adapter;

use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Lazy;
use Switon\Core\PathAliasInterface;
use Switon\Http\AbstractServer;
use Switon\Http\Event\AssetSending;
use Switon\Http\Event\AssetSent;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Server\Adapter\Native\SenderInterface;
use Switon\Http\Server\Event\ServerReady;
use Switon\Http\Server\Network;
use Switon\Http\Server\StaticHandlerInterface;

use function console_log;
use function file_get_contents;
use function get_included_files;
use function header;
use function putenv;
use function readfile;
use function shell_exec;

/**
 * Runs the built-in PHP server flow and native-SAPI fallback.
 *
 * Guidance: Use for local and simple runtime serving; static-file shortcuts stay here, request execution stays in RequestHandler.
 *
 * Road-signs:
 * - cli mode starts php -S
 * - request mode emits RequestReceived
 * - static files short-circuit via StaticHandlerInterface
 * - dynamic requests emit ServerReady then RequestHandler
 * - native sender writes headers and body
 *
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\AbstractServer
 * @see \Switon\Http\Event\RequestReceived
 * @see \Switon\Http\Server\Event\ServerReady
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Http\Server\StaticHandlerInterface
 * @see \Switon\Http\Server\Adapter\Native\SenderInterface
 */
class Php extends AbstractServer
{
    #[Autowired] protected SenderInterface $sender;
    #[Autowired] protected StaticHandlerInterface|Lazy $staticHandler;
    #[Autowired] protected PathAliasInterface $pathAlias;

    /** @var array<string, mixed> PHP-FPM server settings */
    #[Autowired] protected array $settings = [];

    public function __construct(AppInterface $app)
    {
        parent::__construct($app);

        $this->settings['worker_num'] ??= 4;

        $argv = $GLOBALS['argv'] ?? [];
        $this->serverOptions->port = $this->resolvePortFromArgv($argv, $this->serverOptions->port);

        $this->initializeServerGlobals();
    }

    /**
     * @param list<string> $argv
     */
    protected function resolvePortFromArgv(array $argv, int $fallbackPort): int
    {
        foreach ($argv as $k => $v) {
            if (($v === '--port' || $v === '-p') && isset($argv[$k + 1])) {
                return (int)$argv[$k + 1];
            }
        }

        return $fallbackPort;
    }

    protected function initializeServerGlobals(): void
    {
        $server = [
            'REQUEST_SCHEME' => 'http',
            'SERVER_ADDR' => $this->serverOptions->host === '0.0.0.0' ? Network::local() : $this->serverOptions->host,
            'SERVER_PORT' => $this->serverOptions->port,
        ];

        foreach ($server as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }

    protected function prepareGlobals(): void
    {
        $raw = file_get_contents('php://input');
        $rawBody = $raw === false ? null : $raw;
        $this->eventDispatcher->dispatch(new RequestReceived($_GET, $_POST, $_SERVER, $rawBody, $_COOKIE, $_FILES));
    }

    public function start(): void
    {
        if ($this->isCliRuntime()) {
            $this->startCliServer();
            return;
        }

        $this->prepareGlobals();

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if ($this->handleStaticRequest($uri)) {
            return;
        }

        $this->eventDispatcher->dispatch(new ServerReady(null, $this->serverOptions->host, $this->serverOptions->port));
        $this->requestHandler->handle();
    }

    protected function isCliRuntime(): bool
    {
        return PHP_SAPI === 'cli';
    }

    protected function startCliServer(): void
    {
        $workerNum = (int)($this->settings['worker_num'] ?? 4);
        if ($workerNum > 1) {
            putenv("PHP_CLI_SERVER_WORKERS=$workerNum");
        }

        $publicDir = $this->pathAlias->resolve('@public');
        $entryScript = $this->getEntryScript();
        $command = PHP_BINARY . " -S {$this->serverOptions->host}:{$this->serverOptions->port} -t $publicDir  $entryScript";

        $this->logCliStart($command);
        $this->runCliCommand($command);
        $this->terminateProcess();
    }

    protected function getEntryScript(): string
    {
        $included = get_included_files();
        return $included[0] ?? '';
    }

    protected function logCliStart(string $command): void
    {
        console_log('info', $command);
        $prefix = $this->router->getPrefix();
        console_log('info', "http://127.0.0.1:{$this->serverOptions->port}" . ($prefix ?: '/'));
    }

    protected function runCliCommand(string $command): void
    {
        shell_exec($command);
    }

    protected function terminateProcess(): never
    {
        exit(0);
    }

    protected function handleStaticRequest(string $uri): bool
    {
        if (!$this->staticHandler->isFile($uri)) {
            return false;
        }

        // Dispatch AssetSending event before sending
        $this->eventDispatcher->dispatch(new AssetSending($uri));

        $statusCode = 404;
        if (($file = $this->staticHandler->getFile($uri)) !== null) {
            $this->sendHttpHeader('Content-Type: ' . $this->staticHandler->getMimeType($file));
            $this->outputFile($file);
            $statusCode = 200;
        } else {
            $this->sendHttpHeader('HTTP/1.1 404 Not Found');
        }

        // Dispatch AssetSent event after sending
        $this->eventDispatcher->dispatch(new AssetSent($uri, $statusCode));
        return true;
    }

    protected function sendHttpHeader(string $line): void
    {
        header($line);
    }

    protected function outputFile(string $file): void
    {
        readfile($file);
    }

    public function sendHeaders(): void
    {
        $this->sender->sendHeaders();
    }

    public function sendBody(): void
    {
        $this->sender->sendBody();
    }
}
