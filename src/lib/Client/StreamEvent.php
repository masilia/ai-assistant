<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * One event from an AI streaming response.
 *
 * During the stream, the consumer yields one or more "data" events
 * (token text + final flag = false). When the stream completes, it
 * yields one final event with the captured usage data and `isFinal = true`.
 *
 * The shape is the same regardless of provider — the adapter handles
 * per-provider parsing via parseStreamChunk() and extractStreamUsage().
 *
 * @internal
 */
final readonly class StreamEvent
{
    public function __construct(
        public ?string $token,
        public bool    $isFinal,
        public ?array  $usage = null,
        public ?string $finishReason = null,
    )
    {
    }
}
