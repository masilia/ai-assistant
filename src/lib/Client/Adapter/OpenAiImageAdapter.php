<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

/**
 * OpenAI image generation adapter.
 *
 * Calls the /v1/images/generations endpoint. Supports gpt-image-2 and
 * previous image models.
 */
class OpenAiImageAdapter implements ImageProviderAdapterInterface
{
    private const SUPPORTED_SIZES = [
        '1024x1024',
        '1792x1024',
        '1024x1792',
    ];

    public function supportsImageGeneration(string $providerIdentifier): bool
    {
        return $providerIdentifier === ProviderId::OPENAI;
    }

    public function buildImageRequestBody(
        string  $prompt,
        string  $model,
        ?string $size = null,
        ?string $quality = null,
    ): array {
        $body = [
            'model'  => $model,
            'prompt' => $prompt,
            'n'      => 1,
            'size'   => $size ?: '1024x1024',
        ];

        if ($quality !== null) {
            $body['quality'] = $quality;
        }

        return $body;
    }

    public function buildEndpointUrl(?string $customApiUrl): string
    {
        $base = rtrim($customApiUrl ?: 'https://api.openai.com/v1', '/');

        if (!str_ends_with($base, '/images/generations')) {
            $base .= '/images/generations';
        }

        return $base;
    }

    public function buildHeaders(?string $apiKey): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        return $headers;
    }

    public function parseImageResponse(array $data): array
    {
        $dataItem = $data['data'][0] ?? null;
        if ($dataItem === null) {
            throw new \RuntimeException(
                sprintf('OpenAI image API returned no data. Raw: %s', json_encode($data))
            );
        }

        // OpenAI returns either url or b64_json
        if (isset($dataItem['b64_json'])) {
            return [
                'imageData'     => $dataItem['b64_json'],
                'mimeType'      => 'image/png',
                'revisedPrompt' => $dataItem['revised_prompt'] ?? null,
            ];
        }

        if (isset($dataItem['url'])) {
            // Download the image and return as base64
            $imageContent = file_get_contents($dataItem['url']);
            if ($imageContent === false) {
                throw new \RuntimeException(
                    sprintf('Failed to download image from URL: %s', $dataItem['url'])
                );
            }

            $mimeType = mime_content_type($dataItem['url']) ?: 'image/png';

            return [
                'imageData'     => base64_encode($imageContent),
                'mimeType'      => $mimeType,
                'revisedPrompt' => $dataItem['revised_prompt'] ?? null,
            ];
        }

        throw new \RuntimeException(
            sprintf('OpenAI image API returned unexpected data structure. Raw: %s', json_encode($data))
        );
    }

    public function getSupportedSizes(): array
    {
        return self::SUPPORTED_SIZES;
    }

    public function getDefaultImageModel(): string
    {
        return 'gpt-image-2';
    }
}
