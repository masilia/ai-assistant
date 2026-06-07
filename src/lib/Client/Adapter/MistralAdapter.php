<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

class MistralAdapter extends AbstractOpenAiAdapter
{
    protected function getProviderIdentifier(): string
    {
        return 'mistral';
    }

    protected function getDefaultBaseUrl(): string
    {
        return 'https://api.mistral.ai/v1';
    }

    public function getDefaultTestModel(): string
    {
        return 'mistral-small-latest';
    }
}
