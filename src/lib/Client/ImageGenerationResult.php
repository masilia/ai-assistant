<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client;

/**
 * Normalized result from an image generation API call.
 */
final readonly class ImageGenerationResult
{
    public function __construct(
        public string  $imageData,
        public string  $mimeType,
        public ?string $revisedPrompt = null,
    ) {
    }

    /**
     * @return array{imageData: string, mimeType: string, revisedPrompt: string|null}
     */
    public function toArray(): array
    {
        return [
            'imageData'     => $this->imageData,
            'mimeType'      => $this->mimeType,
            'revisedPrompt' => $this->revisedPrompt,
        ];
    }
}
