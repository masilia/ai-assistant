<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

/**
 * MiniMax image generation adapter.
 *
 * Calls the /v1/image_generation endpoint. Supports image-01 and image-01-live.
 */
class MiniMaxImageAdapter implements ImageProviderAdapterInterface
{
    private const SUPPORTED_ASPECT_RATIOS = [
        '1:1',
        '16:9',
        '4:3',
        '3:2',
        '2:3',
        '3:4',
        '9:16',
        '21:9',
    ];

    public function supportsImageGeneration(string $providerIdentifier): bool
    {
        return $providerIdentifier === ProviderId::MINIMAX;
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
        ];

        // MiniMax uses aspect_ratio instead of size
        if ($size !== null && in_array($size, self::SUPPORTED_ASPECT_RATIOS, true)) {
            $body['aspect_ratio'] = $size;
        } elseif ($size !== null) {
            // If a pixel size was passed (e.g. "1024x1024"), map to aspect ratio
            $body['aspect_ratio'] = self::pixelSizeToAspectRatio($size);
        } else {
            $body['aspect_ratio'] = '1:1';
        }

        return $body;
    }

    public function buildEndpointUrl(?string $customApiUrl): string
    {
        $base = rtrim($customApiUrl ?: 'https://api.minimax.io', '/');

        // Strip any trailing path segments that might conflict
        $base = rtrim($base, '/');

        if (!str_ends_with($base, '/v1/image_generation')) {
            $base .= '/v1/image_generation';
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
                sprintf('MiniMax image API returned no data. Raw: %s', json_encode($data))
            );
        }

        // MiniMax returns a URL
        if (isset($dataItem['url'])) {
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

        // Also handle base64 response
        if (isset($dataItem['b64_json'])) {
            return [
                'imageData'     => $dataItem['b64_json'],
                'mimeType'      => 'image/png',
                'revisedPrompt' => $dataItem['revised_prompt'] ?? null,
            ];
        }

        throw new \RuntimeException(
            sprintf('MiniMax image API returned unexpected data structure. Raw: %s', json_encode($data))
        );
    }

    public function getSupportedSizes(): array
    {
        return self::SUPPORTED_ASPECT_RATIOS;
    }

    public function getDefaultImageModel(): string
    {
        return 'image-01';
    }

    /**
     * Map a pixel size string to the closest MiniMax aspect ratio.
     */
    private static function pixelSizeToAspectRatio(string $size): string
    {
        $parts = explode('x', $size);
        if (count($parts) !== 2) {
            return '1:1';
        }

        $w = (int) $parts[0];
        $h = (int) $parts[1];
        if ($h === 0) {
            return '1:1';
        }

        $ratio = $w / $h;

        return match (true) {
            abs($ratio - 1.0) < 0.01   => '1:1',
            abs($ratio - 16 / 9) < 0.05 => '16:9',
            abs($ratio - 4 / 3) < 0.05  => '4:3',
            abs($ratio - 3 / 2) < 0.05  => '3:2',
            abs($ratio - 2 / 3) < 0.05  => '2:3',
            abs($ratio - 3 / 4) < 0.05  => '3:4',
            abs($ratio - 9 / 16) < 0.05 => '9:16',
            abs($ratio - 21 / 9) < 0.05 => '21:9',
            default                      => '1:1',
        };
    }
}
