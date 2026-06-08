<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

class MistralAdapter extends AbstractOpenAiAdapter
{
    protected function getProviderIdentifier(): string
    {
        return ProviderId::MISTRAL;
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
