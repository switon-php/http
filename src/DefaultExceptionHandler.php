<?php

declare(strict_types=1);

namespace Switon\Http;

use Psr\Log\LoggerInterface;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception;
use Switon\Core\StopFlow;
use Switon\Http\Exception\ForbiddenException;
use Switon\Http\Exception\UnauthorizedException;
use Switon\Rendering\RendererInterface;
use Throwable;

use function explode;
use function str_ends_with;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Converts uncaught exceptions into HTTP error responses.
 *
 * Road-signs:
 * - map Exception::getStatusCode() → HTTP status
 * - json/html error body with debug details
 * - 4xx info + debug log
 * - 5xx error log
 * - fallback html when no view template
 *
 * @see \Switon\Core\Exception Status code + json payload
 * @see \Switon\Http\ExceptionHandlerInterface
 * @see \Switon\Http\ExceptionDispatcherInterface
 * @see \Switon\Http\Event\RequestFailed
 * @see \Switon\Rendering\RendererInterface
 * @see \Switon\Http\Exception\UnauthorizedException
 * @see \Switon\Http\Exception\ForbiddenException
 */
class DefaultExceptionHandler implements ExceptionHandlerInterface
{
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected LoggerInterface $logger;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RendererInterface $renderer;

    #[Autowired] protected ?string $format = null;
    #[Autowired] protected string $debugTemplate = '@switon.http.resources/DefaultExceptionHandler/View/Debug';

    /**
     * Handle exception (ExceptionHandlerInterface implementation).
     */
    public function handle(Throwable $throwable): bool
    {
        // Don't handle StopFlow (used for redirects)
        if ($throwable instanceof StopFlow) {
            return false;
        }

        $code = $throwable instanceof Exception ? $throwable->getStatusCode() : 500;

        if ($code >= 500 && $code <= 599) {
            // 5xx errors - server errors (serious)
            $context = $throwable instanceof Exception ? $throwable->getContext() : [];
            $context['exception'] = $throwable;
            $this->logger->error($throwable::class, $context);
        } elseif ($code >= 400 && $code <= 499) {
            // 4xx errors - client errors (normal business flow)
            // Simple info log
            $simpleContext = $throwable instanceof Exception ? $throwable->getContext() : [];
            if ($throwable instanceof UnauthorizedException || $throwable instanceof ForbiddenException) {
                $simpleContext['request_method'] = $this->request->verb();
                $simpleContext['request_url'] = $this->request->url();
            }
            $this->logger->info($throwable::class . ': ' . $throwable->getMessage(), $simpleContext);

            // Detailed debug log with full stack trace
            $detailedContext = $simpleContext;
            $detailedContext['exception'] = $throwable;
            $this->logger->debug($throwable::class, $detailedContext);
        }

        if ($this->format === 'json'
            || $this->isJsonContentType($this->request->header('content-type', ''))
            || $this->request->wantsJson()
        ) {
            if ($throwable instanceof Exception) {
                $status = $throwable->getStatusCode();
                $json = $throwable->getJson();
            } else {
                $status = 500;
                $json = ['code' => $status, 'msg' => 'Internal Server Error'];
            }

            if ($this->app->isDebug()) {
                $json['msg'] = $throwable::class . ': ' . $throwable->getMessage();
                $json['exception'] = explode("\n", (string)$throwable);
                $json['file'] = $throwable->getFile();
                $json['line'] = $throwable->getLine();
            } elseif ($status >= 500) {
                $json['msg'] = 'Internal Server Error';
            }
            $this->response->json($json, $status);
        } else {
            $this->response->text($this->render($throwable, $code), 'text/html', $code);
        }

        return true;
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

    protected function render(Throwable $exception, int $statusCode): string
    {
        if ($this->app->isDebug()) {
            if ($this->renderer->exists('@view/Errors/Debug')) {
                $template = '@view/Errors/Debug';
            } else {
                $template = $this->debugTemplate;
            }
            return $this->renderer->render($template, ['exception' => $exception])->content();
        }

        foreach (
            [
                "@view/Errors/$statusCode",
                '@view/Errors/Error'
            ] as $template
        ) {
            if ($this->renderer->exists($template)) {
                return $this->renderer->render($template, ['statusCode' => $statusCode, 'exception' => $exception])->content();
            }
        }

        $status = $this->response->getStatusText($statusCode);
        return "<html lang='en'><title>$statusCode $status</title><body>$statusCode $status</body></html>";
    }
}
