<?php

declare(strict_types=1);

namespace Switon\Http\Response;

use JsonSerializable;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Json;
use Switon\Core\Lazy;
use Switon\Http\ResponseInterface;

use function is_array;
use function is_string;

/**
 * JSON response renderer.
 *
 * Guidance: Pass arrays/strings when the payload shape is already final; JsonSerializable values are wrapped into the default success envelope here.
 *
 * Road-signs:
 * - sets JSON content type before encoding
 * - uses configured default JSON flags when `render()` gets `0`
 * - JsonSerializable becomes `code` + `msg` + `data`
 *
 * @see \Switon\Http\Response\JsonRendererInterface
 */
class JsonRenderer implements JsonRendererInterface
{
    #[Autowired] protected ResponseInterface|Lazy $response;

    #[Autowired] protected int|string $ok = 0;
    #[Autowired] protected int|string $error = 1;
    #[Autowired] protected int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Render one payload as a JSON response.
     */
    public function render(mixed $data, int $options = 0): ResponseInterface
    {
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');

        // Use configured default if options is 0
        $jsonOptions = $options === 0 ? $this->options : $options;

        // Auto-wrap logic (from existing implementation)
        if (is_array($data) || is_string($data)) {
            //no-op
            $content = $data;
        } elseif ($data instanceof JsonSerializable) {
            $content = ['code' => $this->ok, 'msg' => '', 'data' => $data];
        } else {
            $content = $data;
        }

        $this->response->setContent(Json::stringify($content, $jsonOptions));
        return $this->response;
    }
}
