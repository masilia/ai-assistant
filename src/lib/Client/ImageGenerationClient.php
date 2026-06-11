<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Masilia\AiAssistant\Client\Adapter\ImageProviderAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Provider-agnostic image generation client.
 *
 * Resolves the active image generation provider, builds the request,
 * calls the API, and parses the response into an {@see ImageGenerationResult}.
 */
readonly class ImageGenerationClient
{
    public function __construct(
        private HttpClientInterface  $httpClient,
        private ImageTargetResolver  $targetResolver,
        private ImageAdapterRegistry $adapterRegistry,
    ) {
    }

    /**
     * Generate an image from a text prompt.
     *
     * @throws \RuntimeException When no image generation provider is configured
     * @throws \RuntimeException When the API call fails
     */
    public function generate(
        string  $prompt,
        ?string $size = null,
        ?string $quality = null,
    ): ImageGenerationResult {
        $target = $this->targetResolver->resolve();
        if ($target === null) {
            throw new \RuntimeException(
                'No image generation provider is configured. '
                . 'Set an image model identifier in the admin dashboard or configure masilia_ai_assistant.system.{scope}.image_model in YAML.'
            );
        }

        $adapter = $this->adapterRegistry->getForProvider($target->providerIdentifier);

        $url     = $adapter->buildEndpointUrl($target->apiUrl);
        $headers = $adapter->buildHeaders($target->apiKey);
        $body    = $adapter->buildImageRequestBody(
            $prompt,
            $target->imageModelIdentifier,
            $size,
            $quality,
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'json'    => $body,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            throw new \RuntimeException(
                sprintf(
                    '%s image API returned HTTP %d: %s',
                    ProviderId::displayName($target->providerIdentifier),
                    $statusCode,
                    $response->getContent(false),
                )
            );
        }

        $rawData = $response->toArray();
        $parsed  = $adapter->parseImageResponse($rawData);

        return new ImageGenerationResult(
            imageData:     $parsed['imageData'],
            mimeType:      $parsed['mimeType'],
            revisedPrompt: $parsed['revisedPrompt'],
        );
    }
}
