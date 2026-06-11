<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

class OllamaAdapter extends AbstractOpenAiAdapter
{
    public function getDefaultTestModel(): string
    {
        return 'llama3.1';
    }

    protected function getProviderIdentifier(): string
    {
        return ProviderId::OLLAMA;
    }

    protected function getDefaultHost(): string
    {
        return 'http://localhost:11434';
    }
}
