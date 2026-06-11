<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
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
        private HttpClientInterface         $httpClient,
        private ImageTargetResolver         $targetResolver,
        private ImageAdapterRegistry        $adapterRegistry,
        private SiteAccessServiceInterface  $siteAccessService,
    ) {
    }

    /**
     * Generate an image from a text prompt.
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function generate(
        string  $prompt,
        ?string $size = null,
        ?string $quality = null,
    ): ImageGenerationResult {
        $target = $this->targetResolver->resolve();
        if ($target === null) {
            $siteaccess = $this->siteAccessService->getCurrent()?->name ?? 'default';
            throw new RuntimeException(
                sprintf(
                    'No image generation provider configured for siteaccess "%s". '
                    . 'Assign an image model in the admin dashboard for this siteaccess.',
                    $siteaccess,
                )
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
            throw new RuntimeException(
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
