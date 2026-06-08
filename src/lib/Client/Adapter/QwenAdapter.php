<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

class QwenAdapter extends AbstractOpenAiAdapter
{
    protected function getProviderIdentifier(): string
    {
        return ProviderId::QWEN;
    }

    protected function getDefaultBaseUrl(): string
    {
        return 'https://dashscope.aliyuncs.com/compatible-mode/v1';
    }

    public function getDefaultTestModel(): string
    {
        return 'qwen-turbo';
    }
}
