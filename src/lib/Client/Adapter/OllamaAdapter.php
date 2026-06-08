<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

class OllamaAdapter extends AbstractOpenAiAdapter
{
    protected function getProviderIdentifier(): string
    {
        return ProviderId::OLLAMA;
    }

    protected function getDefaultBaseUrl(): string
    {
        return 'http://localhost:11434/v1';
    }

    public function getDefaultTestModel(): string
    {
        return 'llama3.1';
    }
}
