<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

class OpenAiAdapter extends AbstractOpenAiAdapter
{
    public function getDefaultTestModel(): string
    {
        return 'gpt-4o-mini';
    }

    protected function getProviderIdentifier(): string
    {
        return ProviderId::OPENAI;
    }

    protected function getDefaultHost(): string
    {
        return 'https://api.openai.com';
    }
}
