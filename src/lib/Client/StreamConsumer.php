<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\StreamingProviderAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Consumes a Server-Sent Events stream line-by-line and yields
 * {@see StreamEvent} value objects. Owns the streaming buffering,
 * end-of-stream detection, the split between line-based and
 * remaining-buffer content, and the final-event synthesis that
 * captures the last decoded chunk + last finish reason for the
 * adapter to inspect via extractStreamUsage().
 */
readonly class StreamConsumer
{
    public function __construct(
        private HttpClientInterface $httpClient,
    )
    {
    }

    /**
     * @return \Generator<int, StreamEvent>
     */
    public function consume(ResponseInterface $response, StreamingProviderAdapterInterface $adapter): \Generator
    {
        $buffer = '';
        $lastChunk = null;
        $lastFinish = null;

        foreach ($this->httpClient->stream($response) as $chunk) {
            if ($chunk->isLast()) {
                break;
            }

            $buffer .= $chunk->getContent();

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePos));
                $buffer = substr($buffer, $newlinePos + 1);

                if ($line === '') {
                    continue;
                }

                if ($adapter->isStreamEnd($line)) {
                    yield $this->finalEvent($adapter, $lastChunk, $lastFinish);
                    return;
                }

                $token = $this->processLine($line, $adapter, $lastChunk, $lastFinish);
                if ($token !== null) {
                    yield new StreamEvent($token, false);
                }
            }
        }

        $line = trim($buffer);
        if ($line !== '' && !$adapter->isStreamEnd($line)) {
            $parsed = $this->decodeIfDataLine($line);
            if ($parsed !== null) {
                $lastChunk = $parsed;
            }
        }

        yield $this->finalEvent($adapter, $lastChunk, $lastFinish);
    }

    /**
     * Build the final event. The adapter inspects the last decoded
     * chunk + last finish reason to extract usage data.
     */
    private function finalEvent(
        StreamingProviderAdapterInterface $adapter,
        ?array                            $lastChunk,
        ?string                           $lastFinish,
    ): StreamEvent
    {
        return new StreamEvent(
            token: null,
            isFinal: true,
            usage: $adapter->extractStreamUsage($lastChunk ?? [], $lastFinish),
            finishReason: $lastFinish,
        );
    }

    /**
     * Process one SSE line: decode its JSON payload (updating $lastChunk
     * / $lastFinish by reference) and return the token the adapter
     * extracted, or null for non-data lines.
     */
    private function processLine(
        string                            $line,
        StreamingProviderAdapterInterface $adapter,
        ?array                            &$lastChunk,
        ?string                           &$lastFinish,
    ): ?string
    {
        $parsed = $this->decodeIfDataLine($line);
        if ($parsed !== null) {
            $lastChunk = $parsed;
            $finishInChunk = $adapter->extractFinishReason($parsed);
            if ($finishInChunk !== null) {
                $lastFinish = $finishInChunk;
            }
        }

        return $adapter->parseStreamChunk($line);
    }

    /**
     * If a line is an SSE `data: ...` line, decode its JSON payload.
     * Returns null for `event:` lines, comments, or malformed JSON.
     */
    private function decodeIfDataLine(string $line): ?array
    {
        if (!str_starts_with($line, 'data: ')) {
            return null;
        }
        $json = trim(substr($line, 6));
        if ($json === '' || $json === '[DONE]' || $json === 'DONE') {
            return null;
        }
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($data) ? $data : null;
    }
}
