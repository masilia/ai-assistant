<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

class OpenAiAdapter extends AbstractOpenAiAdapter
{
    protected function getProviderIdentifier(): string
    {
        return 'openai';
    }

    protected function getDefaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    public function getDefaultTestModel(): string
    {
        return 'gpt-4o-mini';
    }
}
