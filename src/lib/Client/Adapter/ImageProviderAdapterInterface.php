<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

/**
 * Contract for providers that support image generation.
 *
 * Parallel to {@see ProviderAdapterInterface} but with request/response
 * shapes specific to image generation endpoints (e.g. /v1/images/generations).
 * Not all providers support image generation — only adapters that implement
 * this interface can be used for the image generation flow.
 */
interface ImageProviderAdapterInterface
{
    /**
     * Whether this adapter supports image generation for the given provider.
     */
    public function supportsImageGeneration(string $providerIdentifier): bool;

    /**
     * Build the request body for the image generation API call.
     *
     * @param string $prompt       The text prompt describing the desired image
     * @param string $model        The model identifier (e.g. "gpt-image-2")
     * @param string|null $size    Image size (e.g. "1024x1024", "1792x1024")
     * @param string|null $quality Image quality (e.g. "standard", "high")
     * @return array<string, mixed> The request body to send as JSON
     */
    public function buildImageRequestBody(
        string $prompt,
        string $model,
        ?string $size = null,
        ?string $quality = null,
    ): array;

    /**
     * Parse the provider's image generation response into a normalized result.
     *
     * @param array<string, mixed> $data The decoded JSON response from the provider
     * @return array{imageData: string, mimeType: string, revisedPrompt: string|null}
     *         imageData is a base64-encoded string (data URI or raw base64)
     */
    public function parseImageResponse(array $data): array;

    /**
     * Return the list of image sizes this provider supports.
     *
     * @return list<string> e.g. ['1024x1024', '1792x1024', '1024x1792']
     */
    public function getSupportedSizes(): array;

    /**
     * Return the default model identifier for image generation.
     */
    public function getDefaultImageModel(): string;
}
