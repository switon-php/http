<?php

declare(strict_types=1);

namespace Switon\Http;

use Stringable;

use function is_string;
use function sprintf;

/**
 * Represents a Server-Sent Events payload.
 *
 * Formats data for SSE protocol. Each event can have id, event type, data, and retry fields.
 *
 * @see \Switon\Http\ResponseInterface Content sink
 */
class SseEvent implements Stringable
{
    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Create a data-only event.
     */
    public static function data(mixed $data): self
    {
        return new self(['data' => is_string($data) ? $data : json_encode($data)]);
    }

    /**
     * Create a named event with data.
     */
    public static function event(string $event, mixed $data): self
    {
        return new self([
            'event' => $event,
            'data' => is_string($data) ? $data : json_encode($data),
        ]);
    }

    /**
     * Create an event with id.
     */
    public function withId(string $id): self
    {
        $clone = clone $this;
        $clone->data['id'] = $id;
        return $clone;
    }

    /**
     * Create an event with retry interval.
     */
    public function withRetry(int $milliseconds): self
    {
        $clone = clone $this;
        $clone->data['retry'] = $milliseconds;
        return $clone;
    }

    public function __toString(): string
    {
        $event = '';
        foreach ($this->data as $key => $value) {
            $event .= sprintf('%s: %s', $key, $value) . "\r\n";
        }
        $event .= "\r\n";

        return $event;
    }
}
