<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

/**
 * Qwen image generation adapter.
 *
 * Calls the DashScope multimodal-generation endpoint (not OpenAI-compatible).
 * Supports qwen-image-2.0-pro, qwen-image-2.0, qwen-image-max, qwen-image-plus.
 */
class QwenImageAdapter implements ImageProviderAdapterInterface
{
    use EndpointUrlHelperTrait;

    private const DEFAULT_HOST = 'https://dashscope.aliyuncs.com';
    private const IMAGE_ENDPOINT = '/api/v1/services/aigc/multimodal-generation/generation';

    private const SUPPORTED_SIZES = [
        '2048*2048',  // 1:1 (default for 2.0 series)
        '2688*1536',  // 16:9 (2.0 series)
        '1536*2688',  // 9:16 (2.0 series)
        '2368*1728',  // 4:3 (2.0 series)
        '1728*2368',  // 3:4 (2.0 series)
        '1664*928',   // 16:9 (max/plus)
        '1472*1104',  // 4:3 (max/plus)
        '1328*1328',  // 1:1 (max/plus)
        '1104*1472',  // 3:4 (max/plus)
        '928*1664',   // 9:16 (max/plus)
    ];

    public function supportsImageGeneration(string $providerIdentifier): bool
    {
        return $providerIdentifier === ProviderId::QWEN;
    }

    public function buildImageRequestBody(
        string  $prompt,
        string  $model,
        ?string $size = null,
        ?string $quality = null,
    ): array {
        $body = [
            'model' => $model,
            'input' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ],
            'parameters' => [
                'n' => 1,
            ],
        ];

        if ($size !== null) {
            $body['parameters']['size'] = $this->sizeToQwenFormat($size);
        } else {
            $body['parameters']['size'] = '2048*2048';
        }

        return $body;
    }

    public function buildEndpointUrl(?string $customApiUrl): string
    {
        $host = self::extractHost($customApiUrl ?: self::DEFAULT_HOST);

        return $host . self::IMAGE_ENDPOINT;
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
        $choices = $data['output']['choices'] ?? [];
        if ($choices === []) {
            throw new \RuntimeException(
                sprintf('Qwen image API returned no choices. Raw: %s', json_encode($data))
            );
        }

        $content = $choices[0]['message']['content'] ?? [];
        if ($content === []) {
            throw new \RuntimeException(
                sprintf('Qwen image API returned empty content. Raw: %s', json_encode($data))
            );
        }

        $imageUrl = $content[0]['image'] ?? null;
        if ($imageUrl === null) {
            throw new \RuntimeException(
                sprintf('Qwen image API returned no image URL. Raw: %s', json_encode($data))
            );
        }

        // Qwen returns image URLs that expire after 24 hours.
        // Download and convert to base64.
        $imageContent = file_get_contents($imageUrl);
        if ($imageContent === false) {
            throw new \RuntimeException(
                sprintf('Failed to download image from Qwen URL: %s', $imageUrl)
            );
        }

        $mimeType = 'image/png';
        $headers = @get_headers($imageUrl, true);
        if (is_array($headers['Content-Type'] ?? null)) {
            $mimeType = $headers['Content-Type'][0] ?? $mimeType;
        } elseif (isset($headers['Content-Type'])) {
            $mimeType = $headers['Content-Type'];
        }
        $mimeType = explode(';', $mimeType)[0];

        return [
            'imageData' => base64_encode($imageContent),
            'mimeType' => $mimeType,
            'revisedPrompt' => null,
        ];
    }

    public function getSupportedSizes(): array
    {
        return self::SUPPORTED_SIZES;
    }

    public function getDefaultImageModel(): string
    {
        return 'qwen-image-2.0-pro';
    }

    /**
     * Convert a pixel size string (e.g. "1024x1024") to Qwen format (e.g. "1024*1024").
     */
    private function sizeToQwenFormat(string $size): string
    {
        return str_replace('x', '*', $size);
    }
}
