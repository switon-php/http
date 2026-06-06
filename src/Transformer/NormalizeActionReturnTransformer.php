<?php

declare(strict_types=1);

namespace Switon\Http\Transformer;

use Switon\Core\Attribute\Autowired;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\Event\RequestInvoked;
use Switon\Http\ResponseInterface;
use Throwable;

use function is_array;
use function is_int;
use function is_string;

/**
 * Normalizes controller action return values to framework response payloads.
 *
 * Guidance: Return ResponseInterface when the action wants full control; otherwise this transformer wraps common scalar/array results into JSON.
 *
 * Road-signs:
 * - listens to RequestInvoked
 * - skips when response content already exists
 * - array/object-ish values become success JSON payloads
 * - string/int become shorthand error/code payloads
 * - Throwable is rethrown into the exception path
 *
 * @see \Switon\Http\Event\RequestInvoked
 * @see \Switon\Http\DefaultExceptionHandler
 * @see \Switon\Viewing\ViewRenderer
 * @see \Switon\Http\Response\JsonRendererInterface
 * @see \Switon\Http\Response\JsonRenderer::render()
 */
class NormalizeActionReturnTransformer
{
    #[Autowired] protected ResponseInterface $response;

    /**
     * Normalize one action return value into response state.
     */
    #[EventListener] public function onInvoked(RequestInvoked $event): void
    {
        // Skip if response content is already set (e.g., by ViewRenderer)
        if ($this->response->hasContent()) {
            return;
        }

        $return = $event->return;

        if ($return === null) {
            $this->response->json(['code' => 0, 'msg' => '']);
        } elseif (is_array($return)) {
            $this->response->json(['code' => 0, 'msg' => '', 'data' => $return]);
        } elseif ($return instanceof ResponseInterface) {
            // ResponseInterface already has content set, no-op
        } elseif (is_string($return)) {
            $this->response->json(['code' => -1, 'msg' => $return]);
        } elseif (is_int($return)) {
            $this->response->json(['code' => $return, 'msg' => '']);
        } elseif ($return instanceof Throwable) {
            throw $return;
        } else {
            $this->response->json(['code' => 0, 'msg' => '', 'data' => $return]);
        }
    }
}
