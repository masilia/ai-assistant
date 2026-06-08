<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\StreamingProviderAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Consumes a Server-Sent Events stream line-by-line and yields decoded tokens
 * via the adapter. Owns the streaming buffering, end-of-stream detection, and
 * the split between line-based and remaining-buffer content.
 */
class StreamConsumer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return \Generator<int, string>
     */
    public function consume(ResponseInterface $response, StreamingProviderAdapterInterface $adapter): \Generator
    {
        $buffer = '';

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
                    return;
                }

                $token = $adapter->parseStreamChunk($line);
                if ($token !== null) {
                    yield $token;
                }
            }
        }

        $line = trim($buffer);
        if ($line !== '' && !$adapter->isStreamEnd($line)) {
            $token = $adapter->parseStreamChunk($line);
            if ($token !== null) {
                yield $token;
            }
        }
    }
}
