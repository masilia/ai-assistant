<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

class OllamaAdapter extends AbstractOpenAiCompatibleAdapter
{
    protected function getProviderIdentifier(): string
    {
        return 'ollama';
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
