<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\AiAssistant\AiConstants;
use Psr\Log\LoggerInterface;
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
 *
 * Telemetry: every call (success or failure) is recorded via
 * {@see RequestLoggerInterface} into `app_ai_request_log`, mirroring
 * {@see AiClient}'s text-call logging.
 */
readonly class ImageGenerationClient implements ImageGeneratorInterface
{
    public function __construct(
        private HttpClientInterface        $httpClient,
        private ImageTargetResolver        $targetResolver,
        private ImageAdapterRegistry       $adapterRegistry,
        private SiteAccessServiceInterface $siteAccessService,
        private LoggerInterface            $aiLogger,
        private ?RequestLoggerInterface    $requestLogger = null,
    )
    {
    }

    public function isConfigured(): bool
    {
        return $this->targetResolver->resolve() !== null;
    }

    /**
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
    ): ImageGenerationResult
    {
        $start = microtime(true);

        $target = $this->targetResolver->resolve();
        if ($target === null) {
            $siteaccess = $this->siteAccessService->getCurrent()?->name ?? AiConstants::DEFAULT_SITEACCESS;
            $this->logFailure($start, 'unknown', 'unknown', $siteaccess, new RuntimeException('No image provider configured'));
            throw new RuntimeException(
                sprintf(
                    'No image generation provider configured for siteaccess "%s". '
                    . 'Assign an image model in the admin dashboard for this siteaccess.',
                    $siteaccess,
                )
            );
        }

        $adapter = $this->adapterRegistry->getForProvider($target->providerIdentifier);
        $providerName = ProviderId::displayName($target->providerIdentifier);
        $siteaccess = $this->siteAccessService->getCurrent()?->name ?? AiConstants::DEFAULT_SITEACCESS;

        $url = $adapter->buildEndpointUrl($target->apiUrl);
        $headers = $adapter->buildHeaders($target->apiKey);
        $body = $adapter->buildImageRequestBody(
            $prompt,
            $target->imageModelIdentifier,
            $size,
            $quality,
        );

        $this->aiLogger->info('[ImageGeneration] Requesting image from {provider}', [
            'provider' => $providerName,
            'model' => $target->imageModelIdentifier,
        ]);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->aiLogger->error('[ImageGeneration] {provider} returned HTTP {status}', [
                    'provider' => $providerName,
                    'status' => $statusCode,
                ]);
                throw new RuntimeException(
                    sprintf(
                        '%s image API returned HTTP %d: %s',
                        $providerName,
                        $statusCode,
                        $response->getContent(false),
                    )
                );
            }

            $rawData = $response->toArray();
            $parsed = $adapter->parseImageResponse($rawData);

            $this->aiLogger->info('[ImageGeneration] Image received from {provider} ({mimeType})', [
                'provider' => $providerName,
                'mimeType' => $parsed['mimeType'],
            ]);

            $this->logSuccess($start, $target->providerIdentifier, $target->imageModelIdentifier, $siteaccess);

            return new ImageGenerationResult(
                imageData: $parsed['imageData'],
                mimeType: $parsed['mimeType'],
                revisedPrompt: $parsed['revisedPrompt'],
            );
        } catch (\Throwable $e) {
            $this->aiLogger->error('[ImageGeneration] Request failed: {message}', [
                'message' => $e->getMessage(),
                'provider' => $providerName,
            ]);
            $this->logFailure($start, $target->providerIdentifier, $target->imageModelIdentifier, $siteaccess, $e);
            throw $e;
        }
    }

    private function logFailure(float $startMs, string $provider, string $model, string $siteaccess, \Throwable $e): void
    {
        $this->requestLogger?->log([
            'providerIdentifier' => $provider,
            'modelIdentifier' => $model,
            'success' => false,
            'latencyMs' => (int)round((microtime(true) - $startMs) * 1000),
            'errorCode' => $e::class,
            'tokensIn' => null,
            'tokensOut' => null,
            'siteaccess' => $siteaccess,
            'finishReason' => null,
        ]);
    }

    private function logSuccess(float $startMs, string $provider, string $model, string $siteaccess): void
    {
        $this->requestLogger?->log([
            'providerIdentifier' => $provider,
            'modelIdentifier' => $model,
            'success' => true,
            'latencyMs' => (int)round((microtime(true) - $startMs) * 1000),
            'errorCode' => null,
            'tokensIn' => null,
            'tokensOut' => null,
            'siteaccess' => $siteaccess,
            'finishReason' => 'image_generated',
        ]);
    }
}
