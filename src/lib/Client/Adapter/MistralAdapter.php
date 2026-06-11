<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

class MistralAdapter extends AbstractOpenAiAdapter
{
    public function getDefaultTestModel(): string
    {
        return 'mistral-small-latest';
    }

    protected function getProviderIdentifier(): string
    {
        return ProviderId::MISTRAL;
    }

    protected function getDefaultHost(): string
    {
        return 'https://api.mistral.ai';
    }
}
