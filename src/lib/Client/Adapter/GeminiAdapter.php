<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

/**
 * Google Gemini adapter using the OpenAI-compatible endpoint.
 *
 * Gemini's OpenAI-compatible endpoint uses a non-standard path prefix
 * (/v1beta/openai) instead of the typical /v1.
 */
class GeminiAdapter extends AbstractOpenAiAdapter
{
    public function getDefaultTestModel(): string
    {
        return 'gemini-2.5-flash';
    }

    protected function getProviderIdentifier(): string
    {
        return ProviderId::GEMINI;
    }

    protected function getDefaultHost(): string
    {
        return 'https://generativelanguage.googleapis.com';
    }

    protected function getChatEndpointPath(): string
    {
        return '/v1beta/openai/chat/completions';
    }
}
